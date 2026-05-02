<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class DdnsLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function newEntryFromRequest(Request $request): DdnsLog
    {
        $log = new DdnsLog();
        $log->setRequestPath($request->getPathInfo());
        $log->setHttpMethod($request->getMethod());
        $log->setUsername($request->query->get('username'));
        $log->setRequestedDomain($request->query->get('domain'));
        $log->setIpaddr($request->query->get('ipaddr') ?? $request->query->get('ipv4'));
        $log->setConfiguredIpv6($request->query->get('ipv6') ?? $request->query->get('ip6addr'));
        $log->setClientIp($request->getClientIp() ?? $request->server->get('REMOTE_ADDR'));
        $log->setUserAgent($request->headers->get('User-Agent'));

        return $log;
    }

    public function save(DdnsLog $log): void
    {
        $log->setMessage($this->sanitizeMessage($log->getMessage()));

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function sanitizeMessage(?string $message): ?string
    {
        if (null === $message || '' === trim($message)) {
            return $message;
        }

        $message = preg_replace('/password=[^&\s]+/i', 'password=***', $message) ?? $message;
        $message = preg_replace('/token=[^&\s]+/i', 'token=***', $message) ?? $message;

        return trim($message);
    }
}
