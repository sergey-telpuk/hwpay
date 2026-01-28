<?php

declare(strict_types=1);

namespace App\Application\Transfer;

interface ExchangeRateProviderInterface
{
    /**
     * Returns the exchange rate from source to target currency (1 unit of source = rate units of target).
     */
    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): float|string;
}
