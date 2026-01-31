<?php

declare(strict_types=1);

namespace App\Domain\Account;

/**
 * Thrown when an account is requested by id but does not exist.
 */
final class AccountNotFoundException extends \DomainException
{
    public static function withId(AccountId $accountId): self
    {
        return new self(sprintf('Account not found: %s', $accountId->toString()));
    }
}
