<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\AccountNotFoundException;

interface AccountRepositoryInterface
{
    /**
     * @throws AccountNotFoundException When account does not exist.
     */
    public function get(AccountId $id): Account;

    /**
     * Locks account for update (pessimistic write lock).
     *
     * @throws AccountNotFoundException When account does not exist.
     */
    public function lockForUpdate(AccountId $id): Account;
}
