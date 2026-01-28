<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Controller;

use App\Application\Transfer\TransferFundsHandler;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use App\Tests\Helper\TransactionalWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
#[CoversClass(TransferFundsHandler::class)]
final class TransferControllerTest extends TransactionalWebTestCase
{
    #[Test]
    public function transferSuccess(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new AccountEntity('acc-from', 10_000));
        $em->persist(new AccountEntity('acc-to', 5_000));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account_id' => 'acc-from',
                'to_account_id' => 'acc-to',
                'amount_minor' => 3_000,
                'idempotency_key' => 'idem-1',
            ]),
        );
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('idem-1', $data['transfer_id']);
        self::assertSame('acc-from', $data['from_account_id']);
        self::assertSame('acc-to', $data['to_account_id']);
        self::assertSame(3_000, $data['amount_minor']);
    }

    #[Test]
    public function transferIdempotentReturnsSameResponse(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new AccountEntity('acc-a', 10_000));
        $em->persist(new AccountEntity('acc-b', 0));
        $em->flush();
        $em->clear();
        $payload = [
            'from_account_id' => 'acc-a',
            'to_account_id' => 'acc-b',
            'amount_minor' => 1_000,
            'idempotency_key' => 'idem-2',
        ];
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );
        self::assertResponseIsSuccessful();
        $first = $client->getResponse()->getContent();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );
        self::assertResponseIsSuccessful();
        self::assertSame($first, $client->getResponse()->getContent());
    }

    #[Test]
    public function transferInsufficientBalanceReturns422(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new AccountEntity('acc-low', 50));
        $em->persist(new AccountEntity('acc-high', 0));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account_id' => 'acc-low',
                'to_account_id' => 'acc-high',
                'amount_minor' => 100,
                'idempotency_key' => 'idem-3',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    #[Test]
    public function transferWithCurrencyConversion(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new AccountEntity('acc-usd', 10_000, 'USD'));
        $em->persist(new AccountEntity('acc-eur', 0, 'EUR'));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account_id' => 'acc-usd',
                'to_account_id' => 'acc-eur',
                'amount_minor' => 10_000,
                'idempotency_key' => 'idem-convert',
            ]),
        );
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('idem-convert', $data['transfer_id']);
        self::assertSame(10_000, $data['amount_minor']);
    }

    #[Test]
    public function transferValidationErrorsReturn422(): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account_id' => '',
                'to_account_id' => 'acc-to',
                'amount_minor' => -1,
                'idempotency_key' => 'key',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('errors', $data);
    }
}
