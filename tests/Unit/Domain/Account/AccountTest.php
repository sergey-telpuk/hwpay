<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\InsufficientBalanceException;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
final class AccountTest extends TestCase
{
    private const string VALID_UUID = '00000000-0000-4000-8000-000000000001';

    #[Test]
    public function balanceReturnsGivenBalance(): void
    {
        $id = new AccountId(self::VALID_UUID);
        $balance = new Money('10000', new Currency('USD'));
        $account = new Account($id, $balance);
        $this->assertSame($balance, $account->balance());
        $this->assertSame($id, $account->id());
    }

    #[Test]
    public function negativeBalanceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Balance cannot be negative');
        new Account(new AccountId(self::VALID_UUID), new Money('-1', new Currency('USD')));
    }

    #[Test]
    public function debitReducesBalance(): void
    {
        $account = new Account(new AccountId(self::VALID_UUID), new Money('10000', new Currency('USD')));
        $account->debit(new Money('3000', new Currency('USD')));
        $this->assertSame('7000', $account->balance()->getAmount());
    }

    #[Test]
    public function debitZeroThrows(): void
    {
        $account = new Account(new AccountId(self::VALID_UUID), new Money('10000', new Currency('USD')));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debit amount must be positive');
        $account->debit(new Money('0', new Currency('USD')));
    }

    #[Test]
    public function debitInsufficientBalanceThrows(): void
    {
        $account = new Account(new AccountId(self::VALID_UUID), new Money('100', new Currency('USD')));
        $this->expectException(InsufficientBalanceException::class);
        $account->debit(new Money('200', new Currency('USD')));
    }

    #[Test]
    public function creditIncreasesBalance(): void
    {
        $account = new Account(new AccountId(self::VALID_UUID), new Money('10000', new Currency('USD')));
        $account->credit(new Money('5000', new Currency('USD')));
        $this->assertSame('15000', $account->balance()->getAmount());
    }

    #[Test]
    public function creditZeroThrows(): void
    {
        $account = new Account(new AccountId(self::VALID_UUID), new Money('10000', new Currency('USD')));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit amount must be positive');
        $account->credit(new Money('0', new Currency('USD')));
    }
}
