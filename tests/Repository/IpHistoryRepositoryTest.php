<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\IpHistory;
use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IpHistoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private IpHistoryRepository $ipHistoryRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->ipHistoryRepository = $container->get(IpHistoryRepository::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->seed();
    }

    public function testSearchByTimestamp(): void
    {
        $at = new \DateTimeImmutable('2026-01-01 12:00:00');
        $ipv4 = $this->ipHistoryRepository->findValidAt(RecordType::A, $at);
        $ipv6 = $this->ipHistoryRepository->findValidAt(RecordType::AAAA, $at);

        self::assertNotNull($ipv4);
        self::assertSame('1.1.1.1', $ipv4?->getIp());
        self::assertNotNull($ipv6);
        self::assertSame('2001:4860:4860::8844', $ipv6?->getIp());
    }

    public function testSearchByInterval(): void
    {
        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to = new \DateTimeImmutable('2026-01-03 00:00:00');

        $ipv4 = $this->ipHistoryRepository->findWithinInterval(RecordType::A, $from, $to);
        $ipv6 = $this->ipHistoryRepository->findWithinInterval(RecordType::AAAA, $from, $to);

        self::assertCount(2, $ipv4);
        self::assertSame('1.1.1.1', $ipv4[0]->getIp());
        self::assertSame('8.8.8.8', $ipv4[1]->getIp());
        self::assertCount(1, $ipv6);
        self::assertSame('2001:4860:4860::8844', $ipv6[0]->getIp());
    }

    private function seed(): void
    {
        $a1 = (new IpHistory())
            ->setRecordType(RecordType::A)
            ->setIp('1.1.1.1')
            ->setValidFrom(new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setValidTo(new \DateTimeImmutable('2026-01-02 00:00:00'))
            ->setSource(IpHistorySource::Fritzbox);
        $a2 = (new IpHistory())
            ->setRecordType(RecordType::A)
            ->setIp('8.8.8.8')
            ->setValidFrom(new \DateTimeImmutable('2026-01-02 00:00:01'))
            ->setSource(IpHistorySource::Fritzbox);
        $aaaa = (new IpHistory())
            ->setRecordType(RecordType::AAAA)
            ->setIp('2001:4860:4860::8844')
            ->setValidFrom(new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setSource(IpHistorySource::Manual);

        $this->entityManager->persist($a1);
        $this->entityManager->persist($a2);
        $this->entityManager->persist($aaaa);
        $this->entityManager->flush();
    }
}

