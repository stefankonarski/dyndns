<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DdnsUpdateService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicDdnsController
{
    #[Route(path: '/update', name: 'app_public_update', methods: ['GET'])]
    public function __invoke(Request $request, DdnsUpdateService $ddnsUpdateService): Response
    {
        $result = $ddnsUpdateService->handle($request);

        return new Response($result->getMessage(), $result->getHttpStatus(), [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}

