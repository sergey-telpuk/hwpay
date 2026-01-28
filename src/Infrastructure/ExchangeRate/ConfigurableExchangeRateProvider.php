<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

use App\Application\Transfer\ExchangeRateProviderInterface;

final class ConfigurableExchangeRateProvider implements ExchangeRateProviderInterface
{
    /** @var array<string, array<string, float|string>> */
    public function __construct(
        private array $exchangeRates,
    ) {
    }

    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): float|string
    {
        if ($sourceCurrencyCode === $targetCurrencyCode) {
            return '1';
        }
        $rate = $this->exchangeRates[$sourceCurrencyCode][$targetCurrencyCode] ?? null;
        if (null === $rate) {
            throw new \InvalidArgumentException(sprintf(
                'Exchange rate not available: %s -> %s',
                $sourceCurrencyCode,
                $targetCurrencyCode,
            ));
        }

        return $rate;
    }
}
