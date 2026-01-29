<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use Money\Currency;
use Money\Money;

interface ExchangeRateProviderInterface
{
    /**
     * Returns the exchange rate from source to target currency (1 unit of source = rate units of target).
     */
    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): string;

    /**
     * Converts amount to target currency using the exchange rate. All conversion goes through Money.
     */
    public function convert(Money $amount, Currency $targetCurrency): Money;
}
