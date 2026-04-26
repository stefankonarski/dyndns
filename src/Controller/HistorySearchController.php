<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
use App\Service\DateTimeInputParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/admin/history')]
class HistorySearchController extends AbstractController
{
    #[Route(path: '', name: 'app_history', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        IpHistoryRepository $ipHistoryRepository,
        DateTimeInputParser $dateTimeInputParser,
    ): Response
    {
        $timestampInput = $this->getInput($request, 'timestamp');
        $fromInput = $this->getInput($request, 'from');
        $toInput = $this->getInput($request, 'to');

        $timestampResult = null;
        $intervalResult = null;

        if ('' !== $timestampInput) {
            $timestamp = $dateTimeInputParser->parse($timestampInput);
            if (null === $timestamp) {
                $this->addFlash('error', 'Ungültiger Timestamp. Erlaubte Formate: YYYY-MM-DDTHH:MM oder ISO-8601.');
            } else {
                $timestampResult = [
                    'at' => $timestamp,
                    'ipv4' => $ipHistoryRepository->findValidAt(RecordType::A, $timestamp),
                    'ipv6' => $ipHistoryRepository->findValidAt(RecordType::AAAA, $timestamp),
                ];
            }
        }

        if ('' !== $fromInput || '' !== $toInput) {
            $from = $dateTimeInputParser->parse($fromInput);
            $to = $dateTimeInputParser->parse($toInput);

            if (null === $from || null === $to) {
                $this->addFlash('error', 'Für Intervall-Suche müssen Start und Ende gültige Timestamps sein.');
            } elseif ($to < $from) {
                $this->addFlash('error', 'Intervall ist ungültig: Ende liegt vor Start.');
            } else {
                $intervalResult = [
                    'from' => $from,
                    'to' => $to,
                    'ipv4' => $ipHistoryRepository->findWithinInterval(RecordType::A, $from, $to),
                    'ipv6' => $ipHistoryRepository->findWithinInterval(RecordType::AAAA, $from, $to),
                ];
            }
        }

        return $this->render('history/search.html.twig', [
            'timestampInput' => $timestampInput,
            'fromInput' => $fromInput,
            'toInput' => $toInput,
            'timestampResult' => $timestampResult,
            'intervalResult' => $intervalResult,
        ]);
    }

    private function getInput(Request $request, string $key): string
    {
        $value = $request->query->get($key);

        if (null === $value || '' === trim((string) $value)) {
            $value = $request->request->get($key, '');
        }

        return trim((string) $value);
    }
}
