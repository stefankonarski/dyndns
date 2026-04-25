<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DnsRecordState;
use App\Enum\RecordType;
use App\Repository\DdnsLogRepository;
use App\Repository\DnsRecordStateRepository;
use App\Service\DdnsConfigService;
use App\Service\HetznerDnsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'app_root', methods: ['GET'])]
    public function root(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route(path: '/admin', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        DdnsConfigService $ddnsConfigService,
        DdnsLogRepository $ddnsLogRepository,
        DnsRecordStateRepository $dnsRecordStateRepository,
        HetznerDnsClient $hetznerDnsClient,
    ): Response {
        $config = $ddnsConfigService->getOrCreate();
        $recordName = $ddnsConfigService->normalizeRecordName($config);

        $aRecord = null;
        $aaaaRecord = null;
        if (null !== $recordName && null !== $config->getZoneId()) {
            $aRecord = $dnsRecordStateRepository->findOneByZoneNameAndType($config->getZoneId(), $recordName, RecordType::A);
            $aaaaRecord = $dnsRecordStateRepository->findOneByZoneNameAndType($config->getZoneId(), $recordName, RecordType::AAAA);
        }

        return $this->render('dashboard/index.html.twig', [
            'config' => $config,
            'recordName' => $recordName,
            'aRecord' => $aRecord,
            'aaaaRecord' => $aaaaRecord,
            'lastContact' => $ddnsLogRepository->findLastContact(),
            'lastSuccess' => $ddnsLogRepository->findLastSuccess(),
            'lastFailure' => $ddnsLogRepository->findLastFailure(),
            'hetznerTokenConfigured' => $hetznerDnsClient->isConfigured(),
            'hetznerReachable' => $hetznerDnsClient->isConfigured() ? $hetznerDnsClient->testToken() : false,
        ]);
    }
}

