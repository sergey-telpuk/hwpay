<?php

declare(strict_types=1);

namespace App\Application\Transfer;

interface TransactionRepositoryInterface
{
    public function findOneByExternalId(string $externalId): ?TransferFundsResult;
}
