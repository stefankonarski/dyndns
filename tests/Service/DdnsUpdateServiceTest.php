<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DdnsConfig;
use App\Entity\DdnsLog;
use App\Enum\DdnsResult;
use App\Service\DdnsAuthenticator;
use App\Service\DdnsConfigService;
use App\Service\DdnsLogger;
use App\Service\DdnsUpdateService;
use App\Service\DnsRecordSynchronizer;
use App\Service\PublicIpValidator;
use App\Service\Value\SyncOutcome;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[AllowMockObjectsWithoutExpectations]
class DdnsUpdateServiceTest extends TestCase
{
    public function testSuccessfulFritzboxRequest(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::once())
            ->method('syncForFritzbox')
            ->willReturn(new SyncOutcome(DdnsResult::Updated, 'A aktualisiert.', true, 'A', 'home.example.org'));

        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'example.org',
            'ipaddr' => '1.1.1.1',
        ], [], [], [], [], ['REMOTE_ADDR' => '10.0.0.10']);

        $response = $service->handle($request);

        self::assertSame(200, $response->getHttpStatus());
        self::assertSame(DdnsResult::Updated, $response->getResult());
        self::assertStringStartsWith('good', $response->getMessage());
    }

    public function testAuthFailed(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::never())->method('syncForFritzbox');
        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'wrong-password',
            'domain' => 'example.org',
            'ipaddr' => '1.1.1.1',
        ]);

        $response = $service->handle($request);
        self::assertSame(401, $response->getHttpStatus());
        self::assertSame(DdnsResult::AuthFailed, $response->getResult());
        self::assertSame('badauth', $response->getMessage());
    }

    public function testInvalidIpv4(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::never())->method('syncForFritzbox');
        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'example.org',
            'ipaddr' => 'not-an-ip',
        ]);

        $response = $service->handle($request);
        self::assertSame(400, $response->getHttpStatus());
        self::assertSame(DdnsResult::ValidationFailed, $response->getResult());
        self::assertSame('badip', $response->getMessage());
    }

    public function testPrivateIpv4Rejected(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::never())->method('syncForFritzbox');
        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'example.org',
            'ipaddr' => '192.168.0.1',
        ]);

        $response = $service->handle($request);
        self::assertSame(400, $response->getHttpStatus());
        self::assertSame(DdnsResult::ValidationFailed, $response->getResult());
        self::assertSame('badip', $response->getMessage());
    }

    public function testDomainMismatch(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::never())->method('syncForFritzbox');
        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'wrong.example.org',
            'ipaddr' => '1.1.1.1',
        ]);

        $response = $service->handle($request);
        self::assertSame(400, $response->getHttpStatus());
        self::assertSame(DdnsResult::ValidationFailed, $response->getResult());
        self::assertSame('notfqdn', $response->getMessage());
    }

    public function testRecordUnchanged(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::once())
            ->method('syncForFritzbox')
            ->willReturn(new SyncOutcome(DdnsResult::Unchanged, 'A unverändert.', false, 'A', 'home.example.org'));

        [$service, $logger] = $this->createService($config, $sync);
        $logger->expects(self::once())->method('save');

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'example.org',
            'ipaddr' => '1.1.1.1',
        ]);

        $response = $service->handle($request);
        self::assertSame(200, $response->getHttpStatus());
        self::assertSame(DdnsResult::Unchanged, $response->getResult());
        self::assertStringStartsWith('nochg', $response->getMessage());
    }

    public function testParallelUpdatesAreLockProtected(): void
    {
        $config = $this->createBaseConfig();
        $sync = $this->createMock(DnsRecordSynchronizer::class);
        $sync->expects(self::never())->method('syncForFritzbox');

        $ddnsConfigService = $this->createMock(DdnsConfigService::class);
        $ddnsConfigService->method('getOrCreate')->willReturn($config);
        $ddnsConfigService->method('normalizeRecordName')->willReturn('home.example.org');
        $logger = $this->createMock(DdnsLogger::class);
        $logger->method('newEntryFromRequest')->willReturn(new DdnsLog());
        $logger->expects(self::once())->method('save');

        $lock = new class() implements SharedLockInterface {
            public function acquire(bool $blocking = false): bool
            {
                return false;
            }
            public function acquireRead(bool $blocking = false): bool
            {
                return false;
            }
            public function refresh(?float $ttl = null): void
            {
            }
            public function isAcquired(): bool
            {
                return false;
            }
            public function release(): void
            {
            }
            public function isExpired(): bool
            {
                return false;
            }
            public function getRemainingLifetime(): ?float
            {
                return null;
            }
        };
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $service = new DdnsUpdateService(
            $ddnsConfigService,
            new DdnsAuthenticator(),
            new PublicIpValidator(),
            $sync,
            $logger,
            $lockFactory,
            $this->createRateLimiterFactory(),
        );

        $request = new Request([
            'username' => 'fritz',
            'password' => 'my-ddns-password',
            'domain' => 'example.org',
            'ipaddr' => '1.1.1.1',
        ]);
        $response = $service->handle($request);

        self::assertSame(500, $response->getHttpStatus());
        self::assertSame(DdnsResult::InternalError, $response->getResult());
        self::assertSame('911 lock_failed', $response->getMessage());
    }

    /**
     * @return array{0: DdnsUpdateService, 1: DdnsLogger&MockObject}
     */
    private function createService(DdnsConfig $config, DnsRecordSynchronizer $sync): array
    {
        $ddnsConfigService = $this->createMock(DdnsConfigService::class);
        $ddnsConfigService->method('getOrCreate')->willReturn($config);
        $ddnsConfigService->method('normalizeRecordName')->willReturn('home.example.org');

        $logger = $this->createMock(DdnsLogger::class);
        $logger->method('newEntryFromRequest')->willReturn(new DdnsLog());

        $service = new DdnsUpdateService(
            $ddnsConfigService,
            new DdnsAuthenticator(),
            new PublicIpValidator(),
            $sync,
            $logger,
            new LockFactory(new FlockStore(sys_get_temp_dir())),
            $this->createRateLimiterFactory(),
        );

        return [$service, $logger];
    }

    private function createBaseConfig(): DdnsConfig
    {
        $authenticator = new DdnsAuthenticator();
        $config = (new DdnsConfig())
            ->setZoneId('zone-1')
            ->setDomain('example.org')
            ->setSubdomain('home')
            ->setFritzboxUsername('fritz')
            ->setFritzboxPasswordHash($authenticator->hashPassword('my-ddns-password'))
            ->setTtl(120)
            ->setIpv4Enabled(true)
            ->setIpv6Enabled(false);

        return $config;
    }

    private function createRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'ddns_update',
            'policy' => 'token_bucket',
            'limit' => 1000,
            'rate' => ['interval' => '1 minute', 'amount' => 1000],
        ], new InMemoryStorage());
    }
}
