<?php

declare(strict_types=1);

namespace App\Application\Transfer;

interface TransactionRepositoryInterface
{
    /**
     * Finds a completed transfer by idempotency key (external_id).
     */
    public function findOneByExternalId(string $externalId): ?TransferFundsResult;
}
