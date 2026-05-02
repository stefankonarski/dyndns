<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsConfig;
use App\Entity\DdnsLog;
use App\Entity\DnsRecordState;
use App\Enum\DdnsResult;
use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\DnsRecordStateRepository;
use App\Service\Exception\ConfigurationException;
use App\Service\Value\SyncOutcome;
use Doctrine\ORM\EntityManagerInterface;

class DnsRecordSynchronizer
{
    public function __construct(
        private readonly HetznerDnsClient $hetznerDnsClient,
        private readonly DdnsConfigService $ddnsConfigService,
        private readonly DnsRecordStateRepository $dnsRecordStateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicIpValidator $publicIpValidator,
        private readonly IpHistoryService $ipHistoryService,
    ) {
    }

    public function syncForFritzbox(
        DdnsConfig $config,
        ?string $ipv4,
        ?string $ipv6,
        ?DdnsLog $logEntry = null,
        bool $force = false,
    ): SyncOutcome
    {
        $targetIpv4 = null;
        if ($config->isIpv4Enabled()) {
            $targetIpv4 = $this->validatePublicIpv4OrFail($ipv4);
        }

        $targetIpv6 = null;
        if ($config->isIpv6Enabled()) {
            $targetIpv6 = $this->validatePublicIpv6OrFail($ipv6);
        }

        return $this->syncInternal(
            $config,
            $targetIpv4,
            $config->isIpv4Enabled(),
            $targetIpv6,
            $config->isIpv6Enabled(),
            $force,
            IpHistorySource::Fritzbox,
            IpHistorySource::Fritzbox,
            $logEntry,
        );
    }

    public function syncFromConfig(DdnsConfig $config, ?string $ipv4 = null, ?DdnsLog $logEntry = null, bool $force = false): SyncOutcome
    {
        $targetIpv4 = null;
        $manageIpv4 = false;
        if (null !== $ipv4 && '' !== trim($ipv4)) {
            $targetIpv4 = $this->validatePublicIpv4OrFail($ipv4);
            $manageIpv4 = true;
        }
        if ($config->isIpv6Enabled() && !$manageIpv4) {
            return new SyncOutcome(
                DdnsResult::Unchanged,
                'Kein Update ausgefuehrt: IPv6 wird nur ueber DynDNS-Request mit "ipv6" oder "ip6addr" aktualisiert.',
                false,
                null,
                $this->ddnsConfigService->normalizeRecordName($config),
            );
        }

        return $this->syncInternal(
            $config,
            $targetIpv4,
            $manageIpv4,
            null,
            !$config->isIpv6Enabled(),
            $force,
            IpHistorySource::Sync,
            IpHistorySource::Manual,
            $logEntry,
        );
    }

    public function deleteAaaaRecord(DdnsConfig $config, ?DdnsLog $logEntry = null): SyncOutcome
    {
        return $this->syncInternal(
            $config,
            null,
            false,
            null,
            true,
            false,
            IpHistorySource::Sync,
            IpHistorySource::Delete,
            $logEntry,
        );
    }

    private function syncInternal(
        DdnsConfig $config,
        ?string $targetIpv4,
        bool $manageIpv4,
        ?string $targetIpv6,
        bool $manageIpv6,
        bool $force,
        IpHistorySource $sourceA,
        IpHistorySource $sourceAaaa,
        ?DdnsLog $logEntry,
    ): SyncOutcome {
        $this->ddnsConfigService->assertValidForUpdate($config);
        if (!$this->hetznerDnsClient->isConfigured()) {
            throw new ConfigurationException('HETZNER_DNS_API_TOKEN ist nicht gesetzt.');
        }

        $zoneId = $config->getZoneId();
        $recordName = $this->ddnsConfigService->normalizeRecordName($config);
        if (null === $zoneId || '' === trim($zoneId) || null === $recordName || '' === trim($recordName)) {
            throw new ConfigurationException('Zone/Domain/Subdomain ist unvollständig konfiguriert.');
        }

        $existingRecords = $this->hetznerDnsClient->listRecords($zoneId, $recordName);

        $messages = [];
        $hetznerCalled = false;
        $created = false;
        $updated = false;
        $deleted = false;
        $recordTypeForLog = null;

        if ($manageIpv4 && $config->isIpv4Enabled()) {
            $action = $this->syncSingleRecord(
                $existingRecords,
                $zoneId,
                $recordName,
                RecordType::A,
                $targetIpv4,
                $config->getTtl(),
                $force,
                $sourceA,
                $logEntry,
            );
            $this->aggregateActionOutcome($action, $messages, $hetznerCalled, $created, $updated, $deleted);
            $recordTypeForLog = 'A';
        }

        if ($manageIpv6) {
            $action = $this->syncSingleRecord(
                $existingRecords,
                $zoneId,
                $recordName,
                RecordType::AAAA,
                $targetIpv6,
                $config->getTtl(),
                $force,
                $sourceAaaa,
                $logEntry,
            );
            $this->aggregateActionOutcome($action, $messages, $hetznerCalled, $created, $updated, $deleted);
            if (null === $recordTypeForLog || 'A' !== $recordTypeForLog) {
                $recordTypeForLog = 'AAAA';
            }
        }

        return new SyncOutcome(
            $this->resolveOverallResult($created, $updated, $deleted),
            implode(' ', array_filter($messages)),
            $hetznerCalled,
            $recordTypeForLog,
            $recordName,
        );
    }

    private function validatePublicIpv4OrFail(?string $ipv4): string
    {
        $ipv4Validation = $this->publicIpValidator->validatePublicIpv4($ipv4);
        if (!$ipv4Validation->isValid()) {
            throw new ConfigurationException($ipv4Validation->getMessage() ?? 'Ungültige IPv4.');
        }

        return trim((string) $ipv4);
    }

    private function validatePublicIpv6OrFail(?string $ipv6): string
    {
        $ipv6Validation = $this->publicIpValidator->validatePublicIpv6($ipv6);
        if (!$ipv6Validation->isValid()) {
            throw new ConfigurationException($ipv6Validation->getMessage() ?? 'Ungültige IPv6.');
        }

        return trim((string) $ipv6);
    }

    /**
     * @param array{result: DdnsResult, called: bool, message: string} $action
     * @param list<string>                                              $messages
     */
    private function aggregateActionOutcome(
        array $action,
        array &$messages,
        bool &$hetznerCalled,
        bool &$created,
        bool &$updated,
        bool &$deleted,
    ): void {
        $messages[] = $action['message'];
        $hetznerCalled = $hetznerCalled || $action['called'];
        $created = $created || DdnsResult::Created === $action['result'];
        $updated = $updated || DdnsResult::Updated === $action['result'];
        $deleted = $deleted || DdnsResult::Deleted === $action['result'];
    }

    private function resolveOverallResult(bool $created, bool $updated, bool $deleted): DdnsResult
    {
        if ($created) {
            return DdnsResult::Created;
        }
        if ($updated) {
            return DdnsResult::Updated;
        }
        if ($deleted) {
            return DdnsResult::Deleted;
        }

        return DdnsResult::Unchanged;
    }

    /**
     * @param list<array{id: string, type: string, name: string, value: string, ttl: int}> $existingRecords
     *
     * @return array{result: DdnsResult, called: bool, message: string}
     */
    private function syncSingleRecord(
        array $existingRecords,
        string $zoneId,
        string $recordName,
        RecordType $recordType,
        ?string $targetValue,
        int $ttl,
        bool $force,
        IpHistorySource $source,
        ?DdnsLog $logEntry,
    ): array {
        $existing = $this->findExistingRecord($existingRecords, $recordType, $recordName);
        $type = $recordType->value;

        if (null === $targetValue || '' === trim($targetValue)) {
            if (null === $existing) {
                return ['result' => DdnsResult::Unchanged, 'called' => false, 'message' => $type.' unverändert (nicht vorhanden).'];
            }

            $this->hetznerDnsClient->deleteRecord($existing['id']);
            $this->deleteRecordState($zoneId, $recordName, $recordType);
            $this->ipHistoryService->trackChange($recordType, null, $source, $logEntry);

            return ['result' => DdnsResult::Deleted, 'called' => true, 'message' => $type.' gelöscht.'];
        }

        if (null === $existing) {
            $created = $this->hetznerDnsClient->createRecord($zoneId, $type, $recordName, $targetValue, $ttl);
            $this->upsertRecordState($zoneId, $recordName, $recordType, $created['id'] ?: null, $created['value'], $created['ttl']);
            $this->ipHistoryService->trackChange($recordType, $created['value'], $source, $logEntry);

            return ['result' => DdnsResult::Created, 'called' => true, 'message' => $type.' erstellt.'];
        }

        $sameValue = trim($existing['value']) === trim($targetValue);
        $sameTtl = (int) $existing['ttl'] === $ttl;
        if (!$force && $sameValue && $sameTtl) {
            $this->upsertRecordState($zoneId, $recordName, $recordType, $existing['id'], $existing['value'], (int) $existing['ttl']);

            return ['result' => DdnsResult::Unchanged, 'called' => false, 'message' => $type.' unverändert.'];
        }

        $updated = $this->hetznerDnsClient->updateRecord($existing['id'], $zoneId, $type, $recordName, $targetValue, $ttl);
        $this->upsertRecordState($zoneId, $recordName, $recordType, $updated['id'] ?: $existing['id'], $updated['value'], $updated['ttl']);
        $this->ipHistoryService->trackChange($recordType, $updated['value'], $source, $logEntry);

        return ['result' => DdnsResult::Updated, 'called' => true, 'message' => $type.' aktualisiert.'];
    }

    /**
     * @param list<array{id: string, type: string, name: string, value: string, ttl: int}> $existingRecords
     *
     * @return array{id: string, type: string, name: string, value: string, ttl: int}|null
     */
    private function findExistingRecord(array $existingRecords, RecordType $recordType, string $recordName): ?array
    {
        $normalizedName = rtrim(mb_strtolower($recordName), '.');
        foreach ($existingRecords as $record) {
            $type = strtoupper((string) ($record['type'] ?? ''));
            $name = rtrim(mb_strtolower((string) ($record['name'] ?? '')), '.');
            if ($type === $recordType->value && $name === $normalizedName) {
                return $record;
            }
        }

        return null;
    }

    private function upsertRecordState(
        string $zoneId,
        string $name,
        RecordType $recordType,
        ?string $recordId,
        string $value,
        int $ttl,
    ): void {
        $state = $this->dnsRecordStateRepository->findOneByZoneNameAndType($zoneId, $name, $recordType);
        if (null === $state) {
            $state = new DnsRecordState();
            $state->setZoneId($zoneId);
            $state->setName($name);
            $state->setRecordType($recordType);
        }

        $state->setRecordId($recordId);
        $state->setValue($value);
        $state->setTtl($ttl);
        $state->setLastSyncedAt(new \DateTimeImmutable());
        $this->entityManager->persist($state);
    }

    private function deleteRecordState(string $zoneId, string $name, RecordType $recordType): void
    {
        $state = $this->dnsRecordStateRepository->findOneByZoneNameAndType($zoneId, $name, $recordType);
        if (null !== $state) {
            $this->entityManager->remove($state);
        }
    }
}
