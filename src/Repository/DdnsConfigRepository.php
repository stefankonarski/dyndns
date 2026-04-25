<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DdnsConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DdnsConfig>
 */
class DdnsConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DdnsConfig::class);
    }

    public function getSingleton(): ?DdnsConfig
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

