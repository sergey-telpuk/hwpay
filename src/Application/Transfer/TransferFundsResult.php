<?php

declare(strict_types=1);

namespace App\Application\Transfer;

final readonly class TransferFundsResult
{
    public function __construct(
        public string $transferId,
        public string $fromAccountId,
        public string $toAccountId,
        public int $amountMinor,
    ) {
    }
}
