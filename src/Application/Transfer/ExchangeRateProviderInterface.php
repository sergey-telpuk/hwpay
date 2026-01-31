<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use InvalidArgumentException;
use Money\Currency;
use Money\Money;

/** Provides exchange rate and conversion for cross-currency transfers. */
interface ExchangeRateProviderInterface
{
    /**
     * Returns the exchange rate from source to target currency (1 unit of source = rate units of target).
     *
     * @throws InvalidArgumentException When the currency pair is not configured or currency code is empty.
     */
    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): string;

    /**
     * Converts amount to target currency using the exchange rate. All conversion goes through Money.
     *
     * @throws InvalidArgumentException When the currency pair is not configured.
     */
    public function convert(Money $amount, Currency $targetCurrency): Money;
}
