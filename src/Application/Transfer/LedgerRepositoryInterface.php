<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use Money\Money;

interface LedgerRepositoryInterface
{
    public function getBalanceForAccount(string $accountId, string $currency): Money;
}
