<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DnsRecordState;
use App\Enum\RecordType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DnsRecordState>
 */
class DnsRecordStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DnsRecordState::class);
    }

    public function findOneByZoneNameAndType(string $zoneId, string $name, RecordType $recordType): ?DnsRecordState
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.zoneId = :zoneId')
            ->andWhere('s.name = :name')
            ->andWhere('s.recordType = :recordType')
            ->setParameter('zoneId', $zoneId)
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setParameter('recordType', $recordType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

