<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Transfer\HoldStatus;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;

final readonly class HoldRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getActiveHoldsSum(string $accountId, string $currency): Money
    {
        if ($currency === '') {
            throw new \InvalidArgumentException('Currency cannot be empty');
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
}
