<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IpHistory;
use App\Enum\RecordType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IpHistory>
 */
class IpHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IpHistory::class);
    }

    public function findCurrentOpenEntry(RecordType $recordType): ?IpHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.recordType = :recordType')
            ->andWhere('h.validTo IS NULL')
            ->setParameter('recordType', $recordType)
            ->orderBy('h.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidAt(RecordType $recordType, \DateTimeImmutable $timestamp): ?IpHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.recordType = :recordType')
            ->andWhere('h.validFrom <= :at')
            ->andWhere('(h.validTo IS NULL OR h.validTo >= :at)')
            ->setParameter('recordType', $recordType)
            ->setParameter('at', $timestamp)
            ->orderBy('h.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<IpHistory>
     */
    public function findWithinInterval(RecordType $recordType, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.recordType = :recordType')
            ->andWhere('h.validFrom <= :to')
            ->andWhere('(h.validTo IS NULL OR h.validTo >= :from)')
            ->setParameter('recordType', $recordType)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('h.validFrom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

