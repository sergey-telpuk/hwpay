<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Controller;

use App\Infrastructure\Http\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends TestCase
{
    #[Test]
    public function healthReturnsOkWhenDatabaseAndCacheSucceed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($this->createStub(Result::class));

        $cache = new ArrayAdapter();

        $controller = new HealthController($connection, $cache);
        $response = ($controller)();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $response->getContent();
        $this->assertNotFalse($data);
        $decoded = json_decode($data, true);
        $this->assertIsArray($decoded);
        /** @var array{status: string, checks: array{database: bool, cache: bool}} $decoded */
        $this->assertSame('ok', $decoded['status']);
        $this->assertTrue($decoded['checks']['database']);
        $this->assertTrue($decoded['checks']['cache']);
    }

    #[Test]
    public function healthReturnsDegradedWhenDatabaseFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('Connection failed'));

        $cache = new ArrayAdapter();

        $controller = new HealthController($connection, $cache);
        $response = ($controller)();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = $response->getContent();
        $this->assertNotFalse($data);
        $decoded = json_decode($data, true);
        $this->assertIsArray($decoded);
        /** @var array{status: string, checks: array{database: bool, cache: bool}} $decoded */
        $this->assertSame('degraded', $decoded['status']);
        $this->assertFalse($decoded['checks']['database']);
        $this->assertTrue($decoded['checks']['cache']);
    }

    #[Test]
    public function healthReturnsDegradedWhenCacheFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($this->createStub(Result::class));

        $cache = $this->createMock(AdapterInterface::class);
        $cache->method('getItem')->with('health_ping')->willThrowException(new \RuntimeException('Redis down'));

        $controller = new HealthController($connection, $cache);
        $response = ($controller)();

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = $response->getContent();
        $this->assertNotFalse($data);
        $decoded = json_decode($data, true);
        $this->assertIsArray($decoded);
        /** @var array{status: string, checks: array{database: bool, cache: bool}} $decoded */
        $this->assertSame('degraded', $decoded['status']);
        $this->assertTrue($decoded['checks']['database']);
        $this->assertFalse($decoded['checks']['cache']);
    }

    #[Test]
    public function homeReturnsAppAndMessage(): void
    {
        $cache = $this->createMock(AdapterInterface::class);

        $controller = new HealthController($this->createMock(Connection::class), $cache);
        $response = $controller->home();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = $response->getContent();
        $this->assertNotFalse($data);
        $decoded = json_decode($data, true);
        $this->assertIsArray($decoded);
        /** @var array{app: string, message: string} $decoded */
        $this->assertSame('hwpay', $decoded['app']);
        $this->assertSame('Hello', $decoded['message']);
    }
}
