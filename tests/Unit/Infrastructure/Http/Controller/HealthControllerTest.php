<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Controller;

use App\Infrastructure\Http\Controller\HealthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthControllerTest extends TestCase
{
    public function testHomeReturnsHelloMessage(): void
    {
        $controller = new HealthController();
        $response = $controller->home();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('hwpay', $data['app']);
        self::assertSame('Hello', $data['message']);
    }

    public function testHealthReturnsOk(): void
    {
        $controller = new HealthController();
        $response = $controller->__invoke();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('ok', $data['status']);
    }
}
