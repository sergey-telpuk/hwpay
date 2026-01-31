<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Transfer\HoldStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;

/** Sums active (non-expired) holds per account and currency for available balance. */
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
            . 'WHERE account_id = :account_id AND amount_currency = :currency AND status = :status '
            . 'AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)';
        $result = $conn->executeQuery($sql, [
            'account_id' => $accountId,
            'currency' => $currency,
            'status' => HoldStatus::Active->value,
        ]);
        $raw = $result->fetchOne();
        $sumMinor = is_scalar($raw) && is_numeric($raw) ? (string) $raw : '0';

        return new Money($sumMinor, $curr);
    }

    /** Marks active holds with expires_at in the past as Expired. Returns number of rows updated. */
    public function markExpiredOverdue(): int
    {
        $conn = $this->em->getConnection();
        $result = $conn->executeStatement(
            "UPDATE holds SET status = :expired WHERE status = :active AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP",
            [
                'expired' => HoldStatus::Expired->value,
                'active' => HoldStatus::Active->value,
            ],
        );

        return is_int($result) ? $result : 0;
    }
}
