<?php

declare(strict_types=1);

namespace App\Domain\Account;

use InvalidArgumentException;

final readonly class AccountId
{
    public function __construct(
        private string $value,
    ) {
        if ('' === $value) {
            throw new InvalidArgumentException('Account ID cannot be empty');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
