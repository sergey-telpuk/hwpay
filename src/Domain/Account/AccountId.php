<?php

declare(strict_types=1);

namespace App\Domain\Account;

use InvalidArgumentException;

final readonly class AccountId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private string $value,
    ) {
        if ('' === $value) {
            throw new InvalidArgumentException('Account ID cannot be empty');
        }
        if (1 !== preg_match(self::UUID_PATTERN, $value)) {
            throw new InvalidArgumentException('Account ID must be a valid UUID');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
