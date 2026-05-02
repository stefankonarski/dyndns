<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DdnsConfig;
use App\Enum\DdnsResult;
use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\DnsRecordStateRepository;
use App\Service\DdnsConfigService;
use App\Service\DnsRecordSynchronizer;
use App\Service\HetznerDnsClient;
use App\Service\IpHistoryService;
use App\Service\PublicIpValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DnsRecordSynchronizerTest extends TestCase
{
    public function testARecordCreated(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();

        $client->method('isConfigured')->willReturn(true);
        $client->method('listRecords')->willReturn([]);
        $client->expects(self::once())
            ->method('createRecord')
            ->with('zone-1', 'A', 'home.example.org', '1.1.1.1', 120)
            ->willReturn(['id' => 'r1', 'type' => 'A', 'name' => 'home.example.org', 'value' => '1.1.1.1', 'ttl' => 120]);
        $client->expects(self::never())->method('updateRecord');
        $history->expects(self::once())->method('trackChange')->with(RecordType::A, '1.1.1.1', IpHistorySource::Fritzbox, null);
        $repo->method('findOneByZoneNameAndType')->willReturn(null);
        $em->expects(self::atLeastOnce())->method('persist');

        $result = $sut->syncForFritzbox($config, '1.1.1.1', null);
        self::assertSame(DdnsResult::Created, $result->getResult());
    }

    public function testARecordUpdated(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();

        $client->method('isConfigured')->willReturn(true);
        $client->method('listRecords')->willReturn([
            ['id' => 'r1', 'type' => 'A', 'name' => 'home.example.org', 'value' => '8.8.8.8', 'ttl' => 120],
        ]);
        $client->expects(self::once())
            ->method('updateRecord')
            ->with('r1', 'zone-1', 'A', 'home.example.org', '1.1.1.1', 120)
            ->willReturn(['id' => 'r1', 'type' => 'A', 'name' => 'home.example.org', 'value' => '1.1.1.1', 'ttl' => 120]);
        $history->expects(self::once())->method('trackChange')->with(RecordType::A, '1.1.1.1', IpHistorySource::Fritzbox, null);
        $repo->method('findOneByZoneNameAndType')->willReturn(null);
        $em->expects(self::atLeastOnce())->method('persist');

        $result = $sut->syncForFritzbox($config, '1.1.1.1', null);
        self::assertSame(DdnsResult::Updated, $result->getResult());
    }

    public function testRecordUnchanged(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();

        $client->method('isConfigured')->willReturn(true);
        $client->method('listRecords')->willReturn([
            ['id' => 'r1', 'type' => 'A', 'name' => 'home.example.org', 'value' => '1.1.1.1', 'ttl' => 120],
        ]);
        $client->expects(self::never())->method('createRecord');
        $client->expects(self::never())->method('updateRecord');
        $client->expects(self::never())->method('deleteRecord');
        $history->expects(self::never())->method('trackChange');
        $repo->method('findOneByZoneNameAndType')->willReturn(null);
        $em->expects(self::atLeastOnce())->method('persist');

        $result = $sut->syncForFritzbox($config, '1.1.1.1', null);
        self::assertSame(DdnsResult::Unchanged, $result->getResult());
    }

    public function testAaaaRecordSet(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();
        $config->setIpv4Enabled(false)->setIpv6Enabled(true);

        $client->method('isConfigured')->willReturn(true);
        $client->method('listRecords')->willReturn([]);
        $client->expects(self::once())
            ->method('createRecord')
            ->with('zone-1', 'AAAA', 'home.example.org', '2001:4860:4860::8888', 120)
            ->willReturn(['id' => 'r6', 'type' => 'AAAA', 'name' => 'home.example.org', 'value' => '2001:4860:4860::8888', 'ttl' => 120]);
        $history->expects(self::once())->method('trackChange')->with(RecordType::AAAA, '2001:4860:4860::8888', IpHistorySource::Fritzbox, null);
        $repo->method('findOneByZoneNameAndType')->willReturn(null);
        $em->expects(self::atLeastOnce())->method('persist');

        $result = $sut->syncForFritzbox($config, null, '2001:4860:4860::8888');
        self::assertSame(DdnsResult::Created, $result->getResult());
        self::assertSame('AAAA', $result->getRecordType());
    }

    public function testAaaaRecordDeleted(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();
        $config->setIpv6Enabled(false);

        $client->method('isConfigured')->willReturn(true);
        $client->method('listRecords')->willReturn([
            ['id' => 'r6', 'type' => 'AAAA', 'name' => 'home.example.org', 'value' => '2001:4860:4860::8888', 'ttl' => 120],
        ]);
        $client->expects(self::once())->method('deleteRecord')->with('r6');
        $history->expects(self::once())->method('trackChange')->with(RecordType::AAAA, null, IpHistorySource::Manual, null);
        $repo->method('findOneByZoneNameAndType')->willReturn(null);
        $em->expects(self::never())->method('remove');

        $result = $sut->syncFromConfig($config);
        self::assertSame(DdnsResult::Deleted, $result->getResult());
    }

    public function testSyncFromConfigWithIpv6EnabledAndNoIpv4ReturnsHint(): void
    {
        $sut = $this->createSynchronizer($client, $history, $repo, $em);
        $config = $this->createBaseConfig();
        $config->setIpv6Enabled(true);

        $client->expects(self::never())->method('listRecords');
        $client->expects(self::never())->method('createRecord');
        $client->expects(self::never())->method('updateRecord');
        $client->expects(self::never())->method('deleteRecord');
        $history->expects(self::never())->method('trackChange');

        $result = $sut->syncFromConfig($config, null, null, true);
        self::assertSame(DdnsResult::Unchanged, $result->getResult());
        self::assertStringContainsString('IPv6', $result->getMessage());
    }

    private function createSynchronizer(
        &$client,
        &$history,
        &$repo,
        &$em,
    ): DnsRecordSynchronizer {
        $client = $this->createMock(HetznerDnsClient::class);
        $history = $this->createMock(IpHistoryService::class);
        $repo = $this->createMock(DnsRecordStateRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $configService = $this->createMock(DdnsConfigService::class);
        $configService->method('assertValidForUpdate');
        $configService->method('normalizeRecordName')->willReturn('home.example.org');

        return new DnsRecordSynchronizer(
            $client,
            $configService,
            $repo,
            $em,
            new PublicIpValidator(),
            $history,
        );
    }

    private function createBaseConfig(): DdnsConfig
    {
        $config = (new DdnsConfig())
            ->setZoneId('zone-1')
            ->setDomain('example.org')
            ->setSubdomain('home')
            ->setFritzboxUsername('fritz')
            ->setFritzboxPasswordHash(password_hash('pw', PASSWORD_ARGON2ID))
            ->setTtl(120)
            ->setIpv4Enabled(true)
            ->setIpv6Enabled(false);

        return $config;
    }
}
