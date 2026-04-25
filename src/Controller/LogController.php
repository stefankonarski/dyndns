<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DdnsLog;
use App\Repository\DdnsLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/admin/logs')]
class LogController extends AbstractController
{
    #[Route(path: '', name: 'app_logs', methods: ['GET'])]
    public function index(Request $request, DdnsLogRepository $ddnsLogRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 25;

        $from = $this->parseDateTime($request->query->get('from'));
        $to = $this->parseDateTime($request->query->get('to'));
        $status = $this->normalizeString($request->query->get('status'));
        $domain = $this->normalizeString($request->query->get('domain'));
        $ip = $this->normalizeString($request->query->get('ip'));
        $recordType = strtoupper($this->normalizeString($request->query->get('recordType')) ?? '');
        $authRaw = $this->normalizeString($request->query->get('auth'));
        $auth = match ($authRaw) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $pagination = $ddnsLogRepository->paginate([
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'domain' => $domain,
            'ip' => $ip,
            'auth' => $auth,
            'recordType' => '' !== $recordType ? $recordType : null,
        ], $page, $perPage);

        return $this->render('log/index.html.twig', [
            'pagination' => $pagination,
            'filters' => [
                'from' => $request->query->get('from'),
                'to' => $request->query->get('to'),
                'status' => $status,
                'domain' => $domain,
                'ip' => $ip,
                'auth' => $authRaw,
                'recordType' => '' !== $recordType ? $recordType : null,
            ],
        ]);
    }

    #[Route(path: '/{id}', name: 'app_log_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(DdnsLog $log): Response
    {
        return $this->render('log/show.html.twig', [
            'log' => $log,
        ]);
    }

    private function parseDateTime(mixed $input): ?\DateTimeImmutable
    {
        if (!is_string($input) || '' === trim($input)) {
            return null;
        }

        $input = trim($input);
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

    private function normalizeString(mixed $input): ?string
    {
        if (!is_string($input)) {
            return null;
        }
        $value = trim($input);

        return '' === $value ? null : $value;
    }
}

