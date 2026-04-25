<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DdnsLog;
use App\Enum\DdnsResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DdnsLog>
 */
class DdnsLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DdnsLog::class);
    }

    /**
     * @param array{
     *   from?: ?\DateTimeImmutable,
     *   to?: ?\DateTimeImmutable,
     *   status?: ?string,
     *   domain?: ?string,
     *   ip?: ?string,
     *   auth?: ?bool,
     *   recordType?: ?string
     * } $filters
     *
     * @return array{items: list<DdnsLog>, total: int, page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if (!empty($filters['from']) && $filters['from'] instanceof \DateTimeImmutable) {
            $qb->andWhere('l.createdAt >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to']) && $filters['to'] instanceof \DateTimeImmutable) {
            $qb->andWhere('l.createdAt <= :to')->setParameter('to', $filters['to']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('l.result = :status')->setParameter('status', DdnsResult::from($filters['status']));
        }
        if (!empty($filters['domain'])) {
            $qb->andWhere('l.requestedDomain = :domain')->setParameter('domain', mb_strtolower(trim((string) $filters['domain'])));
        }
        if (!empty($filters['ip'])) {
            $qb->andWhere('l.ipaddr = :ip')->setParameter('ip', trim((string) $filters['ip']));
        }
        if (array_key_exists('auth', $filters) && null !== $filters['auth']) {
            $qb->andWhere('l.authSuccess = :auth')->setParameter('auth', (bool) $filters['auth']);
        }
        if (!empty($filters['recordType'])) {
            $qb->andWhere('l.recordType = :recordType')->setParameter('recordType', strtoupper(trim((string) $filters['recordType'])));
        }

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $items = iterator_to_array($paginator->getIterator(), false);
        $pages = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'total' => $total,
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    public function findLastContact(): ?DdnsLog
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastSuccess(): ?DdnsLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.result IN (:successes)')
            ->setParameter('successes', [DdnsResult::Unchanged, DdnsResult::Updated, DdnsResult::Created, DdnsResult::Deleted])
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastFailure(): ?DdnsLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.result NOT IN (:successes)')
            ->setParameter('successes', [DdnsResult::Unchanged, DdnsResult::Updated, DdnsResult::Created, DdnsResult::Deleted])
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}

