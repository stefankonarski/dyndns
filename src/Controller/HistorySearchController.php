<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
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
    public function index(Request $request, IpHistoryRepository $ipHistoryRepository): Response
    {
        $timestampInput = $this->getInput($request, 'timestamp');
        $fromInput = $this->getInput($request, 'from');
        $toInput = $this->getInput($request, 'to');

        $timestampResult = null;
        $intervalResult = null;

        if ('' !== $timestampInput) {
            $timestamp = $this->parseDateTime($timestampInput);
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
            $from = $this->parseDateTime($fromInput);
            $to = $this->parseDateTime($toInput);

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

    private function parseDateTime(string $input): ?\DateTimeImmutable
    {
        $input = trim($input);
        if ('' === $input) {
            return null;
        }

        $formats = ['Y-m-d\TH:i', \DateTimeInterface::ATOM, 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $input);
            if (false !== $dt) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($input);
        } catch (\Throwable) {
            return null;
        }
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
