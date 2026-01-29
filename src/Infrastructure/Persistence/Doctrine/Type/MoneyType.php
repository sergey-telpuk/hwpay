<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use Money\Currency;
use Money\Currencies\ISOCurrencies;
use Money\Money;

/**
 * Doctrine custom mapping type: one column stores "decimal|currency" (e.g. "100.000000|USD").
 * Converts to/from Money. Uses ISOCurrencies for subunit (type is stateless).
 *
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/latest/cookbook/custom-mapping-types.html
 */
final class MoneyType extends Type
{
    private const string NAME = 'money';
    private const string SEPARATOR = '|';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $length = $column['length'] ?? 32;

        return $platform->getStringTypeDeclarationSQL(['length' => $length]);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['string', 'null']);
        }

        $parts = explode(self::SEPARATOR, $value, 2);
        if (count($parts) !== 2) {
            throw InvalidFormat::new($value, self::NAME, 'decimal|currency');
        }

        [$decimal, $currencyCode] = $parts;
        if ($currencyCode === '') {
            throw InvalidFormat::new($value, self::NAME, 'decimal|currency');
        }
        $currency = new Currency($currencyCode);
        $currencies = new ISOCurrencies();
        $subunit = $currencies->subunitFor($currency);
        $multiplier = (string) 10 ** $subunit;
        $decimalTrimmed = trim($decimal);
        if (!is_numeric($decimalTrimmed)) {
            $decimalTrimmed = '0';
        }
        /** @var numeric-string $decimalTrimmed */
        $scaled = bcmul($decimalTrimmed, $multiplier, $subunit);
        $minor = bccomp($scaled, '0', $subunit) >= 0 ? bcadd($scaled, '0.5', 0) : bcsub($scaled, '0.5', 0);

        return new Money($minor, $currency);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Money) {
            throw InvalidType::new($value, self::NAME, [Money::class]);
        }

        $minor = $value->getAmount();
        $currency = $value->getCurrency();
        $currencies = new ISOCurrencies();
        $subunit = $currencies->subunitFor($currency);
        $multiplier = (string) 10 ** $subunit;
        $negative = str_starts_with($minor, '-');
        if ($negative) {
            $minor = substr($minor, 1);
        }
        $int = bcdiv($minor, $multiplier, 0);
        $frac = bcmod($minor, $multiplier);
        $frac = str_pad($frac, $subunit, '0', STR_PAD_LEFT);
        $decimal = ($negative ? '-' : '') . $int . '.' . $frac;

        return $decimal . self::SEPARATOR . $currency->getCode();
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
