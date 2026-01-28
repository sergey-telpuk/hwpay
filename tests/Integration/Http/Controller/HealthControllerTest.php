<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('integration')]
final class HealthControllerTest extends WebTestCase
{
    #[Test]
    public function homeReturnsHelloMessage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('hwpay', $data['app']);
        self::assertSame('Hello', $data['message']);
    }

    #[Test]
    public function healthReturnsOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
    }
}
