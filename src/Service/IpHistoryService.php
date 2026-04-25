<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsLog;
use App\Entity\IpHistory;
use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class IpHistoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IpHistoryRepository $ipHistoryRepository,
    ) {
    }

    public function trackChange(
        RecordType $recordType,
        ?string $newIp,
        IpHistorySource $source,
        ?DdnsLog $logEntry = null,
        ?\DateTimeImmutable $now = null,
    ): void {
        $now ??= new \DateTimeImmutable();
        $current = $this->ipHistoryRepository->findCurrentOpenEntry($recordType);

        if (null === $newIp || '' === trim($newIp)) {
            if (null !== $current) {
                $current->setValidTo($now);
                $this->entityManager->persist($current);
            }

            return;
        }

        $newIp = trim($newIp);
        if (null !== $current && $current->getIp() === $newIp) {
            return;
        }

        if (null !== $current) {
            $current->setValidTo($now);
            $this->entityManager->persist($current);
        }

        $history = (new IpHistory())
            ->setRecordType($recordType)
            ->setIp($newIp)
            ->setValidFrom($now)
            ->setSource($source)
            ->setLogEntry($logEntry);
        $this->entityManager->persist($history);
    }
}

