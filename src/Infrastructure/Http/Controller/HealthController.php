<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private AdapterInterface $cache,
    ) {
    }

    #[Route('/health', name: 'health', methods: [Request::METHOD_GET])]
    public function __invoke(): Response
    {
        $checks = ['database' => $this->checkDatabase(), 'cache' => $this->checkCache()];
        $ok = $checks['database'] && $checks['cache'];
        $status = $ok ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse(
            ['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks],
            $status,
        );
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $item = $this->cache->getItem('health_ping');
            $this->cache->save($item);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    #[Route('/', name: 'home', methods: [Request::METHOD_GET])]
    public function home(): Response
    {
        return new JsonResponse(['app' => 'hwpay', 'message' => 'Hello']);
    }
}
