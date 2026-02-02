<?php

declare(strict_types=1);

namespace App\Domain\Account;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class AccountId
{
    public function __construct(
        private string $value,
    ) {
        if ('' === $value) {
            throw new InvalidArgumentException('Account ID cannot be empty');
        }

        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Account ID must be a valid UUID');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
