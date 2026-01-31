<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Controller;

use App\Application\Transfer\AccountRepositoryInterface;
use App\Domain\Account\AccountId;
use App\Domain\Transfer\HoldStatus;
use App\Domain\Transfer\LedgerSide;
use App\Domain\Transfer\TransactionStatus;
use App\Domain\Transfer\TransactionType;
use App\Infrastructure\Http\Controller\TransferController;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\FxTransactionEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\HoldEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\LedgerEntryEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use App\Tests\Helper\TransactionalWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

#[Group('integration')]
#[CoversClass(TransferController::class)]
final class TransferControllerTest extends TransactionalWebTestCase
{
    private const string UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    private const string ACC_FROM = '11111111-1111-4111-8111-111111111111';
    private const string ACC_TO = '22222222-2222-4222-8222-222222222222';
    private const string ACC_A = '33333333-3333-4333-8333-333333333333';
    private const string ACC_B = '44444444-4444-4444-8444-444444444444';
    private const string ACC_LOW = '55555555-5555-4555-8555-555555555555';
    private const string ACC_HIGH = '66666666-6666-4666-8666-666666666666';
    private const string ACC_USD = '77777777-7777-4777-8777-777777777777';
    private const string ACC_EUR = '88888888-8888-4888-8888-888888888888';
    private const string SEED_ACCOUNT = '00000000-0000-4000-8000-000000000001';

    private function getEntityManager(KernelBrowser $client): EntityManagerInterface
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);

        return $em;
    }

    /** @param array<string, mixed> $data */
    private function encodeJson(array $data): string
    {
        $encoded = json_encode($data);
        self::assertNotFalse($encoded);

        return $encoded;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string|false $content): array
    {
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);

        return $data;
    }

    private function account(string $id, string $currency = 'USD'): AccountEntity
    {
        return new AccountEntity(
            Uuid::fromString($id),
            'user',
            '00000000-0000-0000-0000-000000000001',
            $currency,
            'wallet',
            'active',
            new DateTimeImmutable(),
        );
    }

    private function seedBalance(
        EntityManagerInterface $em,
        string $accountId,
        string $amountMinor,
        string $currency = 'USD',
    ): void {
        if ($em->find(AccountEntity::class, Uuid::fromString(self::SEED_ACCOUNT)) === null) {
            $em->persist($this->account(self::SEED_ACCOUNT, $currency));
        }
        $now = new DateTimeImmutable();
        $namespace = Uuid::fromString(self::UUID_NAMESPACE);
        $txId = Uuid::v5($namespace, 'seed-tx-' . $accountId);
        if ($currency === '' || !is_numeric($amountMinor)) {
            throw new InvalidArgumentException('Currency and amount must be non-empty and numeric');
        }
        $amountMoney = new Money($amountMinor, new Currency($currency));
        $em->persist(new TransactionEntity(
            $txId,
            'seed-' . $accountId,
            TransactionType::Payment,
            TransactionStatus::Completed,
            Uuid::fromString(self::SEED_ACCOUNT),
            Uuid::fromString($accountId),
            $amountMoney,
            $now,
            [],
        ));
        $em->persist(new LedgerEntryEntity(
            Uuid::v5($namespace, 'seed-debit-' . $accountId),
            $txId,
            Uuid::fromString(self::SEED_ACCOUNT),
            LedgerSide::Debit,
            $amountMoney,
            $now,
        ));
        $em->persist(new LedgerEntryEntity(
            Uuid::v5($namespace, 'seed-credit-' . $accountId),
            $txId,
            Uuid::fromString($accountId),
            LedgerSide::Credit,
            $amountMoney,
            $now,
        ));
    }

    /** Пустое тело запроса → 400 */
    #[Test]
    public function transferEmptyBodyReturns400(): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '',
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
        self::assertSame('Invalid JSON', $data['error']);
    }

    /** Невалидный JSON → 400 */
    #[Test]
    public function transferInvalidJsonReturns400(): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not json',
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
        self::assertSame('Invalid JSON', $data['error']);
    }

    /** from_account_id === to_account_id → 400 */
    #[Test]
    public function transferSameAccountReturns400(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_FROM));
        $em->flush();
        $this->seedBalance($em, self::ACC_FROM, '10000', 'USD');
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_FROM,
                'to_account_id' => self::ACC_FROM,
                'amount_minor' => 100,
                'idempotency_key' => 'idem-same-acc',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
    }

    /** Счёт получателя не найден → 404 */
    #[Test]
    public function transferAccountNotFoundReturns404(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_FROM));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_FROM,
                'to_account_id' => '99999999-9999-4999-8999-999999999999',
                'amount_minor' => 100,
                'idempotency_key' => 'idem-404',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
    }

    /** Счёт отправителя не найден → 404 */
    #[Test]
    public function transferFromAccountNotFoundReturns404(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_TO));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => '99999999-9999-4999-8999-999999999999',
                'to_account_id' => self::ACC_TO,
                'amount_minor' => 100,
                'idempotency_key' => 'idem-404-from',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
    }

    /** 1) Не хватает денег → 422 */
    #[Test]
    public function transferInsufficientBalanceReturns422(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_LOW));
        $em->persist($this->account(self::ACC_HIGH));
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_LOW,
                'to_account_id' => self::ACC_HIGH,
                'amount_minor' => 100,
                'idempotency_key' => 'idem-3',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
    }

    /** 2) Хватает денег → успех */
    #[Test]
    public function transferSuccess(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_FROM));
        $em->persist($this->account(self::ACC_TO));
        $em->flush();
        $this->seedBalance($em, self::ACC_FROM, '10000', 'USD');
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_FROM,
                'to_account_id' => self::ACC_TO,
                'amount_minor' => 3_000,
                'idempotency_key' => 'idem-1',
            ]),
        );
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('transfer_id', $data);
        self::assertNotEmpty($data['transfer_id']);
        self::assertSame(self::ACC_FROM, $data['from_account_id']);
        self::assertSame(self::ACC_TO, $data['to_account_id']);
        self::assertSame(3_000, $data['amount_minor']);

        $em = $this->getEntityManager($client);
        $em->clear();
        $txId = Uuid::fromString($data['transfer_id']);
        $ledgerEntries = $em->getRepository(LedgerEntryEntity::class)->findBy(['transactionId' => $txId]);
        self::assertCount(2, $ledgerEntries, 'Same-currency transfer must create 2 ledger entries');
        $holds = $em->getRepository(HoldEntity::class)->findBy(['accountId' => Uuid::fromString(self::ACC_FROM), 'status' => HoldStatus::Captured]);
        self::assertCount(1, $holds, 'Transfer must create one hold with status Captured');
    }

    #[Test]
    public function transferIdempotentReturnsSameResponse(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_A));
        $em->persist($this->account(self::ACC_B));
        $em->flush();
        $this->seedBalance($em, self::ACC_A, '5000', 'USD');
        $em->flush();
        $em->clear();
        $payload = [
            'from_account_id' => self::ACC_A,
            'to_account_id' => self::ACC_B,
            'amount_minor' => 1_000,
            'idempotency_key' => 'idem-2',
        ];
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson($payload),
        );
        self::assertResponseIsSuccessful();
        $first = $client->getResponse()->getContent();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson($payload),
        );
        self::assertResponseIsSuccessful();
        self::assertSame($first, $client->getResponse()->getContent());
    }

    /** 3) Кросс-валюта: разная валюта (USD→EUR) → успех с конвертацией */
    #[Test]
    public function transferCrossCurrencySucceeds(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_USD, 'USD'));
        $em->persist($this->account(self::ACC_EUR, 'EUR'));
        $em->flush();
        $this->seedBalance($em, self::ACC_USD, '10000', 'USD');
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_USD,
                'to_account_id' => self::ACC_EUR,
                'amount_minor' => 10_000,
                'idempotency_key' => 'idem-convert',
            ]),
        );
        self::assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('transfer_id', $data);
        self::assertNotEmpty($data['transfer_id']);
        self::assertSame(self::ACC_USD, $data['from_account_id']);
        self::assertSame(self::ACC_EUR, $data['to_account_id']);
        self::assertSame(10_000, $data['amount_minor']);

        // Проверяем конвертацию: 10000 USD × 0.92 = 9200 EUR на счёте получателя
        $accounts = $client->getContainer()->get(AccountRepositoryInterface::class);
        assert($accounts instanceof AccountRepositoryInterface);
        $toAccount = $accounts->get(new AccountId(self::ACC_EUR));
        self::assertSame('9200', $toAccount->balance()->getAmount());
        self::assertSame('EUR', $toAccount->balance()->getCurrency()->getCode());

        $em = $this->getEntityManager($client);
        $em->clear();
        $txId = Uuid::fromString($data['transfer_id']);
        $ledgerEntries = $em->getRepository(LedgerEntryEntity::class)->findBy(['transactionId' => $txId]);
        self::assertCount(4, $ledgerEntries, 'FX transfer must create 4 ledger entries');
        $fxTx = $em->getRepository(FxTransactionEntity::class)->findOneBy(['transactionId' => $txId]);
        self::assertNotNull($fxTx, 'FX transfer must create one fx_transactions row');
        $holds = $em->getRepository(HoldEntity::class)->findBy(['accountId' => Uuid::fromString(self::ACC_USD), 'status' => HoldStatus::Captured]);
        self::assertCount(1, $holds, 'FX transfer must create one hold with status Captured');
    }

    /** 4) Кросс-валюта: курс недоступен (GBP→JPY) → 400 */
    #[Test]
    public function transferCrossCurrencyNoRateReturns400(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_USD, 'GBP'));
        $em->persist($this->account(self::ACC_EUR, 'JPY'));
        $em->flush();
        $this->seedBalance($em, self::ACC_USD, '10000', 'GBP');
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_USD,
                'to_account_id' => self::ACC_EUR,
                'amount_minor' => 1_000,
                'idempotency_key' => 'idem-no-rate',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('error', $data);
    }

    /** 5) Кросс-валюта: одна валюта (USD→USD) → успех */
    #[Test]
    public function transferSameCurrencySucceeds(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager($client);
        $em->persist($this->account(self::ACC_USD, 'USD'));
        $em->persist($this->account(self::ACC_EUR, 'USD'));
        $em->flush();
        $this->seedBalance($em, self::ACC_USD, '10000', 'USD');
        $em->flush();
        $em->clear();
        $client->request(
            Request::METHOD_POST,
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->encodeJson([
                'from_account_id' => self::ACC_USD,
                'to_account_id' => self::ACC_EUR,
                'amount_minor' => 2_000,
                'idempotency_key' => 'idem-same-ccy',
            ]),
        );
        self::assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('transfer_id', $data);
        self::assertSame(self::ACC_USD, $data['from_account_id']);
        self::assertSame(self::ACC_EUR, $data['to_account_id']);
        self::assertSame(2_000, $data['amount_minor']);
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
            $this->encodeJson([
                'from_account_id' => '',
                'to_account_id' => self::ACC_TO,
                'amount_minor' => -1,
                'idempotency_key' => 'key',
            ]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $this->decodeJson($client->getResponse()->getContent());
        self::assertArrayHasKey('errors', $data);
    }
}
