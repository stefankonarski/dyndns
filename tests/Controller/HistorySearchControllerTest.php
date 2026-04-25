<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HistorySearchControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAuthenticatedUserCanOpenHistoryPage(): void
    {
        $admin = (new AdminUser())
            ->setEmail('admin@example.test')
            ->setPasswordHash('dummy');
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin, firewallContext: 'main');
        $crawler = $this->client->request('GET', '/admin/history');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('h1', 'IP-Historie suchen');
        self::assertCount(2, $crawler->filter('form'));
    }
}
