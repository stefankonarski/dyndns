<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\DdnsConfigType;
use App\Service\DdnsAuthenticator;
use App\Service\DdnsConfigService;
use App\Service\DnsRecordSynchronizer;
use App\Service\HetznerDnsClient;
use App\Service\HetznerZoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/admin/config')]
class ConfigController extends AbstractController
{
    #[Route(path: '', name: 'app_config', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        DdnsConfigService $ddnsConfigService,
        HetznerZoneService $hetznerZoneService,
        DdnsAuthenticator $ddnsAuthenticator,
        DnsRecordSynchronizer $dnsRecordSynchronizer,
        HetznerDnsClient $hetznerDnsClient,
        EntityManagerInterface $entityManager,
    ): Response {
        $config = $ddnsConfigService->getOrCreate();

        $zones = [];
        if ($hetznerDnsClient->isConfigured()) {
            try {
                $zones = $hetznerZoneService->listZones();
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Hetzner-Zonen konnten nicht geladen werden: '.$e->getMessage());
            }
        }
        $zoneChoices = [];
        foreach ($zones as $zone) {
            $zoneChoices[$zone['name']] = $zone['id'].'|'.$zone['name'];
        }

        $currentZone = null;
        if (null !== $config->getZoneId() && null !== $config->getDomain()) {
            $candidate = $config->getZoneId().'|'.$config->getDomain();
            if (in_array($candidate, array_values($zoneChoices), true)) {
                $currentZone = $candidate;
            }
        }

        $form = $this->createForm(DdnsConfigType::class, $config, [
            'zone_choices' => $zoneChoices,
            'current_zone' => $currentZone,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $zoneSelection = (string) $form->get('zoneSelection')->getData();
            if (!in_array($zoneSelection, array_values($zoneChoices), true)) {
                $form->get('zoneSelection')->addError(new FormError('Ungültige Zone ausgewählt.'));
            }

            $password = (string) $form->get('fritzboxPassword')->getData();
            if ('' !== $password && mb_strlen($password) < 10) {
                $form->get('fritzboxPassword')->addError(new FormError('DynDNS-Passwort muss mindestens 10 Zeichen haben.'));
            }

            if (!$config->isIpv4Enabled() && !$config->isIpv6Enabled()) {
                $form->addError(new FormError('Mindestens IPv4 oder IPv6 muss aktiviert sein.'));
            }

            if ($form->isValid()) {
                [$zoneId, $domain] = explode('|', $zoneSelection, 2);
                $config->setZoneId($zoneId);
                $config->setDomain($domain);

                if ('' !== $password) {
                    $config->setFritzboxPasswordHash($ddnsAuthenticator->hashPassword($password));
                }

                $config->setManualIpv6(null);

                $entityManager->persist($config);
                $entityManager->flush();

                if ($form->get('deleteAaaa')->isClicked()) {
                    try {
                        $dnsRecordSynchronizer->deleteAaaaRecord($config);
                        $entityManager->flush();
                        $this->addFlash('success', 'AAAA-Record wurde gelöscht.');
                    } catch (\Throwable $e) {
                        $this->addFlash('error', 'AAAA-Record konnte nicht gelöscht werden: '.$e->getMessage());
                    }

                    return $this->redirectToRoute('app_config');
                }

                if ($form->get('forceSync')->isClicked()) {
                    try {
                        $dnsRecordSynchronizer->syncFromConfig($config, null, null, true);
                        $entityManager->flush();
                        $this->addFlash('success', 'Force-Sync wurde ausgeführt.');
                    } catch (\Throwable $e) {
                        $this->addFlash('error', 'Force-Sync fehlgeschlagen: '.$e->getMessage());
                    }

                    return $this->redirectToRoute('app_config');
                }

                $this->addFlash('success', 'Konfiguration gespeichert.');

                return $this->redirectToRoute('app_config');
            }
        }

        return $this->render('config/edit.html.twig', [
            'form' => $form->createView(),
            'hetznerTokenConfigured' => $hetznerDnsClient->isConfigured(),
            'hetznerReachable' => $hetznerDnsClient->isConfigured() ? $hetznerDnsClient->testToken() : false,
        ]);
    }
}
