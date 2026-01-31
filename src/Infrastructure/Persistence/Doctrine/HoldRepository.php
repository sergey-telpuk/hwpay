<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Transfer\HoldStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;

/** Sums active holds per account and currency for available balance. */
final readonly class HoldRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getActiveHoldsSum(string $accountId, string $currency): Money
    {
        if ($currency === '') {
            throw new InvalidArgumentException('Currency cannot be empty');
        }
        $curr = new Currency($currency);
        $conn = $this->em->getConnection();
        $sql = 'SELECT COALESCE(SUM(amount_amount), 0) FROM holds '
            . 'WHERE account_id = :account_id AND amount_currency = :currency AND status = :status';
        $result = $conn->executeQuery($sql, [
            'account_id' => $accountId,
            'currency' => $currency,
            'status' => HoldStatus::Active->value,
        ]);
        $raw = $result->fetchOne();
        $sumMinor = is_scalar($raw) && is_numeric($raw) ? (string) $raw : '0';

        return new Money($sumMinor, $curr);
    }
}
