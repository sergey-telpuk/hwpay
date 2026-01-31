<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\InsufficientBalanceException;
use App\Domain\Transfer\HoldStatus;
use App\Domain\Transfer\LedgerSide;
use App\Domain\Transfer\TransactionStatus;
use App\Domain\Transfer\TransactionType;
use App\Infrastructure\Persistence\Doctrine\Entity\FxTransactionEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\HoldEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\LedgerEntryEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Transfer flow:
 * 1. Idempotency: Redis first, then DB (IdempotencyStore); return existing result if found.
 * 2. Lock both accounts (deterministic order) and check available balance (ledger − active holds).
 * 3. Create hold (Active) on source account → reserves amount.
 * 4. Try: resolve FX rate, debit/credit in memory, persist transaction + ledger entries,
 *    set hold→Captured, transaction→Completed, flush.
 * 5. On error: if EM open → set hold→Released, transaction→Failed, detach partial ledger/fx,
 *    persist transaction, flush; then rethrow.
 *
 * @throws InvalidArgumentException When from and to account are the same.
 * @throws InsufficientBalanceException When source available balance is insufficient.
 */
#[AsMessageHandler(bus: 'command_bus')]
final readonly class TransferFundsHandler
{
    /** Technical FX accounts: sold-currency leg (FX_SOLD_POOL) and bought-currency leg (FX_BOUGHT_POOL). */
    private const string FX_DEBIT_ACCOUNT_ID  = '00000000-0000-0000-0000-000000000001';
    private const string FX_CREDIT_ACCOUNT_ID = '00000000-0000-0000-0000-000000000002';

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private ClockInterface $clock,
        private ExchangeRateProviderInterface $exchangeRates,
        private IdempotencyStoreInterface $idempotencyStore,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TransferFundsCommand $command): TransferFundsResult
    {
        if ($command->fromAccountId->toString() === $command->toAccountId->toString()) {
            throw new InvalidArgumentException('fromAccountId must differ from toAccountId');
        }

        $existing = $this->idempotencyStore->get($command->idempotencyKey);
        if ($existing instanceof \App\Application\Transfer\TransferFundsResult) {
            return $existing;
        }

        $fromAccountUuid = Uuid::fromString($command->fromAccountId->toString());
        $toAccountUuid = Uuid::fromString($command->toAccountId->toString());
        $fromKey = $command->fromAccountId->toString();
        $toKey = $command->toAccountId->toString();

        if ($fromKey < $toKey) {
            $from = $this->accounts->lockForUpdate($command->fromAccountId);
            $to = $this->accounts->lockForUpdate($command->toAccountId);
        } else {
            $to = $this->accounts->lockForUpdate($command->toAccountId);
            $from = $this->accounts->lockForUpdate($command->fromAccountId);
        }

        $fromCurrency = $from->balance()->getCurrency()->getCode();
        $toCurrency = $to->balance()->getCurrency()->getCode();
        $debitAmount = new Money((string) $command->amountMinor, new Currency($fromCurrency));

        if ($from->balance()->lessThan($debitAmount)) {
            throw InsufficientBalanceException::forAccount(
                $command->fromAccountId,
                $from->balance(),
                $debitAmount,
            );
        }

        $now = $this->clock->now();
        $hold = new HoldEntity(
            Uuid::v4(),
            $fromAccountUuid,
            $debitAmount,
            HoldStatus::Active,
            $now,
            'transfer',
        );
        $this->em->persist($hold);

        $transactionId = Uuid::v4();
        $transaction = new TransactionEntity(
            $transactionId,
            $command->idempotencyKey,
            TransactionType::Payment,
            TransactionStatus::Pending,
            $fromAccountUuid,
            $toAccountUuid,
            $debitAmount,
            $now,
            [],
        );

        try {
            $creditAmount = $debitAmount;
            $rateStr = '1';
            $spreadStr = '0';
            $isFx = $fromCurrency !== $toCurrency;

            if ($isFx) {
                $rateStr = $this->exchangeRates->getExchangeRate($fromCurrency, $toCurrency);
                $creditAmount = $this->exchangeRates->convert($debitAmount, new Currency($toCurrency));
            }

            $from->debit($debitAmount);
            $to->credit($creditAmount);

            $this->em->persist($transaction);

            if ($isFx) {
                $this->persistFxLedgerEntries(
                    $transactionId,
                    $fromAccountUuid,
                    $toAccountUuid,
                    $debitAmount,
                    $creditAmount,
                    $rateStr,
                    $spreadStr,
                    $now,
                );
            } else {
                $this->persistSameCurrencyLedgerEntries(
                    $transactionId,
                    $fromAccountUuid,
                    $toAccountUuid,
                    $debitAmount,
                    $creditAmount,
                    $now,
                );
            }

            $hold->setStatus(HoldStatus::Captured);
            $transaction->setStatus(TransactionStatus::Completed);
            $this->em->flush();
        } catch (\Throwable $e) {
            if ($this->em->isOpen()) {
                $hold->setStatus(HoldStatus::Released);
                $transaction->setStatus(TransactionStatus::Failed);
                $this->detachScheduledLedgerAndFx();
                $this->em->persist($transaction);
                $this->em->flush();
            }
            throw $e;
        }

        $this->logger->info('Transfer completed', [
            'transfer_id' => $transactionId->toRfc4122(),
            'from' => $fromKey,
            'to' => $toKey,
            'debit' => $debitAmount->getAmount() . ' ' . $debitAmount->getCurrency()->getCode(),
            'credit' => $creditAmount->getAmount() . ' '
                . $creditAmount->getCurrency()->getCode(),
            'rate' => $rateStr,
            'spread' => $spreadStr,
        ]);

        $result = new TransferFundsResult(
            transferId: $transactionId->toRfc4122(),
            fromAccountId: $fromKey,
            toAccountId: $toKey,
            amount: $debitAmount,
        );
        $this->idempotencyStore->set($command->idempotencyKey, $result);

        return $result;
    }

    private function persistSameCurrencyLedgerEntries(
        Uuid $transactionId,
        Uuid $fromAccountUuid,
        Uuid $toAccountUuid,
        Money $debitAmount,
        Money $creditAmount,
        DateTimeImmutable $now,
    ): void {
        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $fromAccountUuid,
            LedgerSide::Debit,
            $debitAmount,
            $now,
        ));
        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $toAccountUuid,
            LedgerSide::Credit,
            $creditAmount,
            $now,
        ));
    }

    private function persistFxLedgerEntries(
        Uuid $transactionId,
        Uuid $fromAccountUuid,
        Uuid $toAccountUuid,
        Money $debitAmount,
        Money $creditAmount,
        string $rateStr,
        string $spreadStr,
        DateTimeImmutable $now,
    ): void {
        $fxDebitUuid = Uuid::fromString(self::FX_DEBIT_ACCOUNT_ID);
        $fxCreditUuid = Uuid::fromString(self::FX_CREDIT_ACCOUNT_ID);

        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $fromAccountUuid,
            LedgerSide::Debit,
            $debitAmount,
            $now,
        ));
        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $fxDebitUuid,
            LedgerSide::Credit,
            $debitAmount,
            $now,
        ));
        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $fxCreditUuid,
            LedgerSide::Debit,
            $creditAmount,
            $now,
        ));
        $this->em->persist(new LedgerEntryEntity(
            Uuid::v4(),
            $transactionId,
            $toAccountUuid,
            LedgerSide::Credit,
            $creditAmount,
            $now,
        ));
        $this->em->persist(new FxTransactionEntity(
            Uuid::v4(),
            $transactionId,
            $debitAmount,
            $creditAmount,
            $rateStr,
            $spreadStr,
            $now,
        ));
    }

    private function detachScheduledLedgerAndFx(): void
    {
        $toDetach = [];
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof LedgerEntryEntity || $entity instanceof FxTransactionEntity) {
                $toDetach[] = $entity;
            }
        }
        foreach ($toDetach as $entity) {
            $this->em->detach($entity);
        }
    }
}
