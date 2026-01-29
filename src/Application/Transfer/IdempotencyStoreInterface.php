<?php

declare(strict_types=1);

namespace App\Application\Transfer;

/**
 * Idempotency: get returns cached result (Redis first, then DB); set stores result after successful transfer.
 */
interface IdempotencyStoreInterface
{
    public function get(string $idempotencyKey): ?TransferFundsResult;

    public function set(string $idempotencyKey, TransferFundsResult $result): void;
}
