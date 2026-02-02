<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\ExchangeRate;

use App\Infrastructure\ExchangeRate\ConfigurableExchangeRateProvider;
use InvalidArgumentException;
use Money\Currency;
use Money\Currencies\ISOCurrencies;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurableExchangeRateProvider::class)]
final class ConfigurableExchangeRateProviderTest extends TestCase
{
    private ConfigurableExchangeRateProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new ConfigurableExchangeRateProvider(
            [
                'USD' => ['EUR' => '0.92'],
                'EUR' => ['USD' => '1.09'],
            ],
            new ISOCurrencies(),
        );
    }

    #[Test]
    public function getExchangeRateSameCurrencyReturnsOne(): void
    {
        $this->assertSame('1', $this->provider->getExchangeRate('USD', 'USD'));
        $this->assertSame('1', $this->provider->getExchangeRate('EUR', 'EUR'));
    }

    #[Test]
    public function getExchangeRateUsdToEurReturnsConfiguredRate(): void
    {
        $this->assertSame('0.92', $this->provider->getExchangeRate('USD', 'EUR'));
    }

    #[Test]
    public function getExchangeRateEurToUsdReturnsConfiguredRate(): void
    {
        $this->assertSame('1.09', $this->provider->getExchangeRate('EUR', 'USD'));
    }

    #[Test]
    public function getSpreadReturnsZero(): void
    {
        $this->assertSame('0', $this->provider->getSpread('USD', 'USD'));
        $this->assertSame('0', $this->provider->getSpread('USD', 'EUR'));
    }

    #[Test]
    public function convertSameCurrencyReturnsSameAmount(): void
    {
        $amount = new Money('10000', new Currency('USD'));
        $result = $this->provider->convert($amount, new Currency('USD'));
        $this->assertSame('10000', $result->getAmount());
        $this->assertSame('USD', $result->getCurrency()->getCode());
    }

    #[Test]
    public function convertUsdToEur(): void
    {
        $amount = new Money('10000', new Currency('USD'));
        $result = $this->provider->convert($amount, new Currency('EUR'));
        $this->assertSame('9200', $result->getAmount());
        $this->assertSame('EUR', $result->getCurrency()->getCode());
    }

    #[Test]
    public function getExchangeRateUnconfiguredPairThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->provider->getExchangeRate('GBP', 'JPY');
    }

    #[Test]
    public function getExchangeRateEmptySourceCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be empty');
        $this->provider->getExchangeRate('', 'USD');
    }

    #[Test]
    public function getExchangeRateEmptyTargetCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be empty');
        $this->provider->getExchangeRate('USD', '');
    }

    #[Test]
    public function getSpreadEmptyCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be empty');
        $this->provider->getSpread('', 'USD');
    }

    #[Test]
    public function getSpreadEmptyTargetCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be empty');
        $this->provider->getSpread('USD', '');
    }

    #[Test]
    public function convertUnconfiguredPairThrows(): void
    {
        $amount = new Money('10000', new Currency('GBP'));
        $this->expectException(InvalidArgumentException::class);
        $this->provider->convert($amount, new Currency('JPY'));
    }

    #[Test]
    public function buildFixedListSkipsEmptyOrNonStringKeysAndNormalizesNonNumericRate(): void
    {
        $provider = new ConfigurableExchangeRateProvider(
            [
                '' => ['EUR' => '1'],
                'USD' => ['' => '1', 'EUR' => '0.92', 'X' => 'not-a-number'],
            ],
            new ISOCurrencies(),
        );
        $this->assertSame('0.92', $provider->getExchangeRate('USD', 'EUR'));
        $this->assertSame('0', $provider->getExchangeRate('USD', 'X'));
    }

    #[Test]
    public function buildFixedListSkipsNonStringKeysAndAcceptsFloatRate(): void
    {
        /** @var array<string, array<string, float|string>> $rates - int keys are skipped by buildFixedList */
        $rates = [
            0 => ['EUR' => '1'],
            'USD' => [0 => '1', 'EUR' => 0.92],
        ];
        $provider = new ConfigurableExchangeRateProvider($rates, new ISOCurrencies());
        $this->assertSame('0.92', $provider->getExchangeRate('USD', 'EUR'));
    }
}
