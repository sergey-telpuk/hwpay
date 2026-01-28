<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\AccountId;

final readonly class TransferFundsCommand
{
    public function __construct(
        public AccountId $fromAccountId,
        public AccountId $toAccountId,
        public int $amountMinor,
        public string $idempotencyKey,
    ) {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive');
        }
        if ('' === $idempotencyKey) {
            throw new \InvalidArgumentException('Idempotency key cannot be empty');
        }
    }
}
