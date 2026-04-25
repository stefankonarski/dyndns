<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\IpHistory;
use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
use App\Service\IpHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IpHistoryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private IpHistoryRepository $ipHistoryRepository;
    private IpHistoryService $ipHistoryService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->ipHistoryRepository = $container->get(IpHistoryRepository::class);
        $this->ipHistoryService = $container->get(IpHistoryService::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testIpHistoryForIpv4SwitchIsTracked(): void
    {
        $old = (new IpHistory())
            ->setRecordType(RecordType::A)
            ->setIp('1.1.1.1')
            ->setValidFrom(new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setSource(IpHistorySource::Fritzbox);
        $this->entityManager->persist($old);
        $this->entityManager->flush();

        $now = new \DateTimeImmutable('2026-01-02 00:00:00');
        $this->ipHistoryService->trackChange(RecordType::A, '8.8.8.8', IpHistorySource::Fritzbox, null, $now);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $entries = $this->ipHistoryRepository->findBy(['recordType' => RecordType::A], ['validFrom' => 'ASC']);
        self::assertCount(2, $entries);
        self::assertSame('1.1.1.1', $entries[0]->getIp());
        self::assertNotNull($entries[0]->getValidTo());
        self::assertSame($now->format('Y-m-d H:i:s'), $entries[0]->getValidTo()?->format('Y-m-d H:i:s'));
        self::assertSame('8.8.8.8', $entries[1]->getIp());
    }

    public function testIpHistoryForIpv6SwitchIsTracked(): void
    {
        $old = (new IpHistory())
            ->setRecordType(RecordType::AAAA)
            ->setIp('2001:4860:4860::8844')
            ->setValidFrom(new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setSource(IpHistorySource::Manual);
        $this->entityManager->persist($old);
        $this->entityManager->flush();

        $now = new \DateTimeImmutable('2026-01-03 00:00:00');
        $this->ipHistoryService->trackChange(RecordType::AAAA, '2001:4860:4860::8888', IpHistorySource::Manual, null, $now);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $entries = $this->ipHistoryRepository->findBy(['recordType' => RecordType::AAAA], ['validFrom' => 'ASC']);
        self::assertCount(2, $entries);
        self::assertSame('2001:4860:4860::8844', $entries[0]->getIp());
        self::assertNotNull($entries[0]->getValidTo());
        self::assertSame('2001:4860:4860::8888', $entries[1]->getIp());
    }
}

