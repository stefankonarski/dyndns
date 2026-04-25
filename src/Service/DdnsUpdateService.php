<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsLog;
use App\Enum\DdnsResult;
use App\Service\Exception\ConfigurationException;
use App\Service\Exception\HetznerDnsException;
use App\Service\Value\DdnsUpdateResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class DdnsUpdateService
{
    public function __construct(
        private readonly DdnsConfigService $ddnsConfigService,
        private readonly DdnsAuthenticator $ddnsAuthenticator,
        private readonly PublicIpValidator $publicIpValidator,
        private readonly DnsRecordSynchronizer $dnsRecordSynchronizer,
        private readonly DdnsLogger $ddnsLogger,
        private readonly LockFactory $lockFactory,
        #[Autowire(service: 'limiter.ddns_update')]
        private readonly RateLimiterFactory $ddnsRateLimiter,
    ) {
    }

    public function handle(Request $request): DdnsUpdateResponse
    {
        $startedAt = microtime(true);
        $config = $this->ddnsConfigService->getOrCreate();
        $log = $this->ddnsLogger->newEntryFromRequest($request);
        $log->setConfiguredIpv6($config->getManualIpv6());
        $log->setNormalizedRecordName($this->ddnsConfigService->normalizeRecordName($config));

        $response = null;
        try {
            $rateLimit = $this->ddnsRateLimiter->create($this->buildRateLimitKey($request))->consume(1);
            if (!$rateLimit->isAccepted()) {
                $log->setResult(DdnsResult::ValidationFailed);
                $log->setMessage('Rate limit überschritten.');
                $response = new DdnsUpdateResponse(429, DdnsResult::ValidationFailed, '911 rate_limited');

                return $response;
            }

            $username = $request->query->get('username');
            $password = $request->query->get('password');
            $domain = mb_strtolower(trim((string) $request->query->get('domain')));
            $ipaddr = trim((string) $request->query->get('ipaddr'));

            if (!$this->ddnsAuthenticator->verify($config, $username, $password)) {
                $log->setAuthSuccess(false);
                $log->setResult(DdnsResult::AuthFailed);
                $log->setMessage('DynDNS Auth fehlgeschlagen.');
                $response = new DdnsUpdateResponse(401, DdnsResult::AuthFailed, 'badauth');

                return $response;
            }
            $log->setAuthSuccess(true);

            $configuredDomain = mb_strtolower(trim((string) $config->getDomain()));
            if ('' === $configuredDomain || $configuredDomain !== $domain) {
                $log->setResult(DdnsResult::ValidationFailed);
                $log->setMessage('Angefragte Domain passt nicht zur Konfiguration.');
                $response = new DdnsUpdateResponse(400, DdnsResult::ValidationFailed, 'notfqdn');

                return $response;
            }

            if ($config->isIpv4Enabled()) {
                $ipv4Validation = $this->publicIpValidator->validatePublicIpv4($ipaddr);
                if (!$ipv4Validation->isValid()) {
                    $log->setResult(DdnsResult::ValidationFailed);
                    $log->setMessage($ipv4Validation->getMessage());
                    $response = new DdnsUpdateResponse(400, DdnsResult::ValidationFailed, 'badip');

                    return $response;
                }
            }

            $lock = $this->lockFactory->createLock($this->buildLockKey($config), 15.0);
            if (!$lock->acquire(true)) {
                $log->setResult(DdnsResult::InternalError);
                $log->setMessage('Konnte Update-Lock nicht erwerben.');
                $response = new DdnsUpdateResponse(500, DdnsResult::InternalError, '911 lock_failed');

                return $response;
            }

            try {
                $outcome = $this->dnsRecordSynchronizer->syncForFritzbox($config, $ipaddr, $log);
            } finally {
                $lock->release();
            }

            $log->setResult($outcome->getResult());
            $log->setMessage($outcome->getMessage());
            $log->setHetznerCalled($outcome->isHetznerCalled());
            $log->setRecordType($outcome->getRecordType());
            $log->setNormalizedRecordName($outcome->getRecordName());

            $response = match ($outcome->getResult()) {
                DdnsResult::Unchanged => new DdnsUpdateResponse(200, DdnsResult::Unchanged, 'nochg '.$outcome->getRecordName()),
                DdnsResult::Created, DdnsResult::Updated, DdnsResult::Deleted => new DdnsUpdateResponse(200, $outcome->getResult(), 'good '.$outcome->getRecordName()),
                default => new DdnsUpdateResponse(500, DdnsResult::InternalError, '911 internal_error'),
            };

            return $response;
        } catch (ConfigurationException $e) {
            $status = str_contains(mb_strtolower($e->getMessage()), 'token') ? 503 : 400;
            $log->setResult(DdnsResult::ConfigError);
            $log->setMessage($e->getMessage());

            return new DdnsUpdateResponse($status, DdnsResult::ConfigError, '911 config_error');
        } catch (HetznerDnsException $e) {
            $log->setResult(DdnsResult::HetznerError);
            $log->setMessage($e->getMessage());

            return new DdnsUpdateResponse(502, DdnsResult::HetznerError, '911 hetzner_error');
        } catch (\Throwable $e) {
            $log->setResult(DdnsResult::InternalError);
            $log->setMessage('Interner Fehler beim DynDNS-Update.');

            return new DdnsUpdateResponse(500, DdnsResult::InternalError, '911 internal_error');
        } finally {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $log->setDurationMs(max(0, $durationMs));
            if (!$log->isHetznerCalled() && null !== $response) {
                $log->setHetznerCalled($response->getResult() === DdnsResult::Created || $response->getResult() === DdnsResult::Updated || $response->getResult() === DdnsResult::Deleted);
            }
            $this->ddnsLogger->save($log);
        }
    }

    private function buildRateLimitKey(Request $request): string
    {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $username = (string) $request->query->get('username');

        return sha1($clientIp.'|'.$username);
    }

    private function buildLockKey(\App\Entity\DdnsConfig $config): string
    {
        $zoneId = $config->getZoneId() ?? 'nozone';
        $recordName = $this->ddnsConfigService->normalizeRecordName($config) ?? 'norecord';

        return 'ddns_update_'.$zoneId.'_'.$recordName;
    }
}

