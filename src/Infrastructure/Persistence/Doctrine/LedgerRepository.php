<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Transfer\LedgerRepositoryInterface;
use App\Domain\Transfer\LedgerSide;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;

final readonly class LedgerRepository implements LedgerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getBalanceForAccount(string $accountId, string $currency): Money
    {
        if ($currency === '') {
            throw new \InvalidArgumentException('Currency cannot be empty');
        }
        $curr = new Currency($currency);
        $conn = $this->em->getConnection();
        $sql = 'SELECT COALESCE(SUM(CASE WHEN side = :credit THEN amount_amount ELSE -amount_amount END), 0) '
            . 'FROM ledger_entries WHERE account_id = :account_id AND amount_currency = :currency';
        $result = $conn->executeQuery($sql, [
            'credit' => LedgerSide::Credit->value,
            'account_id' => $accountId,
            'currency' => $currency,
        ]);
        $raw = $result->fetchOne();
        $sumMinor = is_scalar($raw) && is_numeric($raw) ? (string) $raw : '0';

        return new Money($sumMinor, $curr);
    }
}
