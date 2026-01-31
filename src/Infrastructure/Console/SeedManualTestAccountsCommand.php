<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\LedgerEntryEntity;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use App\Domain\Transfer\LedgerSide;
use App\Domain\Transfer\TransactionStatus;
use App\Domain\Transfer\TransactionType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds four accounts for manual API testing (same-currency and cross-currency).
 * Run once after migrations: make migrate && docker compose run --rm app php bin/console app:seed-manual-test-accounts
 */
#[AsCommand(
    name: 'app:seed-manual-test-accounts',
    description: 'Create accounts with balance for manual transfer testing (USD/USD and USD/EUR)',
)]
final class SeedManualTestAccountsCommand extends Command
{
    private const SEED_ACCOUNT_ID = '00000000-0000-4000-8000-000000000001';
    private const ACCOUNT_A_ID = '00000000-0000-0000-0000-000000000010';
    private const ACCOUNT_B_ID = '00000000-0000-0000-0000-000000000011';
    private const ACCOUNT_USD_ID = '00000000-0000-0000-0000-000000000020';
    private const ACCOUNT_EUR_ID = '00000000-0000-0000-0000-000000000021';
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable();
        $namespace = Uuid::fromString(self::UUID_NAMESPACE);

        if ($this->em->find(AccountEntity::class, Uuid::fromString(self::SEED_ACCOUNT_ID)) === null) {
            $this->em->persist(new AccountEntity(
                Uuid::fromString(self::SEED_ACCOUNT_ID),
                'system',
                'seed',
                'USD',
                'internal',
                'active',
                $now,
            ));
        }

        $accountsToCreate = [
            self::ACCOUNT_A_ID => ['USD', '10000'],
            self::ACCOUNT_B_ID => ['USD', '5000'],
            self::ACCOUNT_USD_ID => ['USD', '20000'],
            self::ACCOUNT_EUR_ID => ['EUR', '0'],
        ];
        foreach ($accountsToCreate as $accountId => [$currency, $amountMinor]) {
            if ($this->em->find(AccountEntity::class, Uuid::fromString($accountId)) === null) {
                $this->em->persist(new AccountEntity(
                    Uuid::fromString($accountId),
                    'user',
                    'manual-test',
                    $currency,
                    'wallet',
                    'active',
                    $now,
                ));
            }
        }

        foreach (
            [
                self::ACCOUNT_A_ID => '10000',
                self::ACCOUNT_B_ID => '5000',
                self::ACCOUNT_USD_ID => '20000',
            ] as $accountId => $amountMinor
        ) {
            $amount = new Money($amountMinor, new Currency('USD'));
            $txId = Uuid::v5($namespace, 'seed-tx-' . $accountId);
            if ($this->em->find(TransactionEntity::class, $txId) !== null) {
                continue;
            }
            $this->em->persist(new TransactionEntity(
                $txId,
                'seed-' . $accountId,
                TransactionType::Payment,
                TransactionStatus::Completed,
                Uuid::fromString(self::SEED_ACCOUNT_ID),
                Uuid::fromString($accountId),
                $amount,
                $now,
                [],
            ));
            $this->em->persist(new LedgerEntryEntity(
                Uuid::v5($namespace, 'seed-debit-' . $accountId),
                $txId,
                Uuid::fromString(self::SEED_ACCOUNT_ID),
                LedgerSide::Debit,
                $amount,
                $now,
            ));
            $this->em->persist(new LedgerEntryEntity(
                Uuid::v5($namespace, 'seed-credit-' . $accountId),
                $txId,
                Uuid::fromString($accountId),
                LedgerSide::Credit,
                $amount,
                $now,
            ));
        }

        $this->em->flush();
        $io->success(
            'Accounts ready: '
            . self::ACCOUNT_A_ID . ' (100 USD), '
            . self::ACCOUNT_B_ID . ' (50 USD), '
            . self::ACCOUNT_USD_ID . ' (200 USD), '
            . self::ACCOUNT_EUR_ID . ' (0 EUR, for cross-currency). Use http/transfer.http to test POST /api/transfer.'
        );

        return Command::SUCCESS;
    }
}
