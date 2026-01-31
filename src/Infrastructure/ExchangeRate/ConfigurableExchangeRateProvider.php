<?php

declare(strict_types=1);

namespace App\Infrastructure\ExchangeRate;

use App\Application\Transfer\ExchangeRateProviderInterface;
use InvalidArgumentException;
use Money\Converter;
use Money\Currency;
use Money\Currencies;
use Money\Exchange\FixedExchange;
use Money\Exception\UnresolvableCurrencyPairException;
use Money\Money;

/** Exchange rates from config (parameters.exchange_rates); throws on missing pair. */
final readonly class ConfigurableExchangeRateProvider implements ExchangeRateProviderInterface
{
    private Converter $converter;

    /** @param array<string, array<string, float|string>> $exchangeRates */
    public function __construct(
        private array $exchangeRates,
        Currencies $currencies,
    ) {
        $list = $this->buildFixedList($exchangeRates);
        $this->converter = new Converter($currencies, new FixedExchange($list));
    }

    /** @param array<string, array<string, float|string>> $rates
     * @return array<string, array<string, string>>
     */
    private function buildFixedList(array $rates): array
    {
        $list = [];
        foreach ($rates as $base => $quotes) {
            foreach ($quotes as $counter => $rate) {
                $list[$base][$counter] = is_string($rate) ? $rate : (string) $rate;
            }
        }

        return $list;
    }

    public function getExchangeRate(string $sourceCurrencyCode, string $targetCurrencyCode): string
    {
        if ($sourceCurrencyCode === $targetCurrencyCode) {
            return '1';
        }
        $source = $this->ensureNonEmptyCurrencyCode($sourceCurrencyCode);
        $target = $this->ensureNonEmptyCurrencyCode($targetCurrencyCode);
        try {
            $pair = new FixedExchange($this->buildFixedList($this->exchangeRates))
                ->quote(new Currency($source), new Currency($target));

            return $pair->getConversionRatio();
        } catch (UnresolvableCurrencyPairException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    /** @return non-empty-string */
    private function ensureNonEmptyCurrencyCode(string $code): string
    {
        if ($code === '') {
            throw new InvalidArgumentException('Currency code cannot be empty');
        }

        return $code;
    }

    public function convert(Money $amount, Currency $targetCurrency): Money
    {
        try {
            return $this->converter->convert($amount, $targetCurrency, Money::ROUND_HALF_UP);
        } catch (UnresolvableCurrencyPairException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }
}
