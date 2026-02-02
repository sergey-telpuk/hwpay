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
     * Same currency returns '1'.
     *
     * @throws InvalidArgumentException When the currency pair is not configured or currency code is empty.
     */
    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): string;

    /**
     * Returns the spread for the pair (e.g. '0' when no spread). Same currency returns '0'.
     *
     * @throws InvalidArgumentException When the currency pair is not configured or currency code is empty.
     */
    public function getSpread(string $sourceCurrencyCode, string $targetCurrencyCode): string;

    /**
     * Converts amount to target currency using the exchange rate. Same currency returns same amount.
     * All conversion goes through Money.
     *
     * @throws InvalidArgumentException When the currency pair is not configured.
     */
    public function convert(Money $amount, Currency $targetCurrency): Money;
}
