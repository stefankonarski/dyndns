<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DdnsLog;
use App\Service\DdnsLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DdnsLoggerTest extends TestCase
{
    public function testNoSecretsInLog(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $logger = new DdnsLogger($entityManager);
        $log = new DdnsLog();
        $log->setMessage('Auth failed password=secret123 token=abc123');

        $logger->save($log);

        self::assertStringNotContainsString('secret123', (string) $log->getMessage());
        self::assertStringNotContainsString('abc123', (string) $log->getMessage());
        self::assertStringContainsString('password=***', (string) $log->getMessage());
        self::assertStringContainsString('token=***', (string) $log->getMessage());
    }
}

