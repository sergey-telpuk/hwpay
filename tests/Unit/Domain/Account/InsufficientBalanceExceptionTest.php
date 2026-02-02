<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account;

use App\Domain\Account\AccountId;
use App\Domain\Account\InsufficientBalanceException;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsufficientBalanceException::class)]
final class InsufficientBalanceExceptionTest extends TestCase
{
    #[Test]
    public function forAccountBuildsMessageWithAccountAndAmounts(): void
    {
        $accountId = new AccountId('00000000-0000-4000-8000-000000000001');
        $balance = new Money('100', new Currency('USD'));
        $required = new Money('200', new Currency('USD'));
        $e = InsufficientBalanceException::forAccount($accountId, $balance, $required);
        $this->assertStringContainsString('00000000-0000-4000-8000-000000000001', $e->getMessage());
        $this->assertStringContainsString('100 USD', $e->getMessage());
        $this->assertStringContainsString('200 USD', $e->getMessage());
    }
}
