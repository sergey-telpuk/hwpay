<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use Money\Money;

/** Result of a successful fund transfer (idempotency or new). */
final readonly class TransferFundsResult
{
    public function __construct(
        public string $transferId,
        public string $fromAccountId,
        public string $toAccountId,
        public Money $amount,
    ) {
    }
}
