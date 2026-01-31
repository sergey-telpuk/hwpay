<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    #[Route('/health', name: 'health', methods: [Request::METHOD_GET])]
    public function __invoke(): Response
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/', name: 'home', methods: [Request::METHOD_GET])]
    public function home(): Response
    {
        return new JsonResponse(['app' => 'hwpay', 'message' => 'Hello']);
    }
}
