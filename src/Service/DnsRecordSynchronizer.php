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

    public function syncForFritzbox(DdnsConfig $config, string $ipv4, ?DdnsLog $logEntry = null, bool $force = false): SyncOutcome
    {
        $ipv4Validation = $this->publicIpValidator->validatePublicIpv4($ipv4);
        if (!$ipv4Validation->isValid()) {
            throw new ConfigurationException($ipv4Validation->getMessage() ?? 'Ungültige IPv4.');
        }

        return $this->syncInternal(
            $config,
            $config->isIpv4Enabled() ? trim($ipv4) : null,
            true,
            $this->resolveConfiguredIpv6Target($config),
            true,
            $force,
            IpHistorySource::Fritzbox,
            IpHistorySource::Sync,
            $logEntry,
        );
    }

    public function syncFromConfig(DdnsConfig $config, ?string $ipv4 = null, ?DdnsLog $logEntry = null, bool $force = false): SyncOutcome
    {
        $targetIpv4 = null;
        $manageIpv4 = false;
        if (null !== $ipv4 && '' !== trim($ipv4)) {
            $ipv4Validation = $this->publicIpValidator->validatePublicIpv4($ipv4);
            if (!$ipv4Validation->isValid()) {
                throw new ConfigurationException($ipv4Validation->getMessage() ?? 'Ungültige IPv4.');
            }
            $targetIpv4 = trim($ipv4);
            $manageIpv4 = true;
        }

        return $this->syncInternal(
            $config,
            $targetIpv4,
            $manageIpv4,
            $this->resolveConfiguredIpv6Target($config),
            true,
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

    private function resolveConfiguredIpv6Target(DdnsConfig $config): ?string
    {
        if (!$config->isIpv6Enabled()) {
            return null;
        }

        $ipv6 = $config->getManualIpv6();
        if (null === $ipv6 || '' === trim($ipv6)) {
            throw new ConfigurationException('IPv6 ist aktiviert, aber keine manuelle IPv6-Adresse gesetzt.');
        }

        $ipv6Validation = $this->publicIpValidator->validatePublicIpv6($ipv6);
        if (!$ipv6Validation->isValid()) {
            throw new ConfigurationException($ipv6Validation->getMessage() ?? 'Ungültige IPv6.');
        }

        return trim($ipv6);
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
            $messages[] = $action['message'];
            $hetznerCalled = $hetznerCalled || $action['called'];
            $created = $created || $action['result'] === DdnsResult::Created;
            $updated = $updated || $action['result'] === DdnsResult::Updated;
            $deleted = $deleted || $action['result'] === DdnsResult::Deleted;
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
            $messages[] = $action['message'];
            $hetznerCalled = $hetznerCalled || $action['called'];
            $created = $created || $action['result'] === DdnsResult::Created;
            $updated = $updated || $action['result'] === DdnsResult::Updated;
            $deleted = $deleted || $action['result'] === DdnsResult::Deleted;
            if (null === $recordTypeForLog || 'A' !== $recordTypeForLog) {
                $recordTypeForLog = 'AAAA';
            }
        }

        $result = DdnsResult::Unchanged;
        if ($created) {
            $result = DdnsResult::Created;
        } elseif ($updated) {
            $result = DdnsResult::Updated;
        } elseif ($deleted) {
            $result = DdnsResult::Deleted;
        }

        return new SyncOutcome(
            $result,
            implode(' ', array_filter($messages)),
            $hetznerCalled,
            $recordTypeForLog,
            $recordName,
        );
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

