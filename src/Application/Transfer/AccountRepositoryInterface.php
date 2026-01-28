<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;

interface AccountRepositoryInterface
{
    public function get(AccountId $id): Account;

    public function save(Account $account): void;

    public function lockForUpdate(AccountId $id): Account;
}
