<?php

declare(strict_types=1);

namespace App\Domain\Account;

use Money\Money;

final class InsufficientBalanceException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAccount(AccountId $accountId, Money $balance, Money $required): self
    {
        return new self(sprintf(
            'Insufficient balance for account %s: has %s, required %s',
            $accountId->toString(),
            self::formatMoney($balance),
            self::formatMoney($required),
        ));
    }

    private static function formatMoney(Money $money): string
    {
        $minor = (int) $money->getAmount();
        $major = number_format($minor / 100, 2, '.', '');

        return $major.' '.$money->getCurrency()->getCode();
    }
}
