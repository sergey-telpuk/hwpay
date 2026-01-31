<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use Money\Money;

interface LedgerRepositoryInterface
{
    /**
     * Returns the sum of ledger entries (credits minus debits) for the given account and currency.
     */
    public function getBalanceForAccount(string $accountId, string $currency): Money;
}
