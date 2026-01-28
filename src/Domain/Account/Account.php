<?php

declare(strict_types=1);

namespace App\Domain\Account;

use Money\Money;

final class Account
{
    public function __construct(
        private readonly AccountId $id,
        private Money $balance,
    ) {
        if ($balance->isNegative()) {
            throw new \InvalidArgumentException('Balance cannot be negative');
        }
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function debit(Money $amount): void
    {
        if ($amount->isZero() || $amount->isNegative()) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }
        if ($this->balance->lessThan($amount)) {
            throw InsufficientBalanceException::forAccount($this->id, $this->balance, $amount);
        }
        $this->balance = $this->balance->subtract($amount);
    }

    public function credit(Money $amount): void
    {
        if ($amount->isZero() || $amount->isNegative()) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }
        $this->balance = $this->balance->add($amount);
    }
}
