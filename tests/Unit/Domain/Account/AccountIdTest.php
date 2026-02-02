<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account;

use App\Domain\Account\AccountId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountId::class)]
final class AccountIdTest extends TestCase
{
    #[Test]
    public function acceptsValidUuid(): void
    {
        $uuid = '00000000-0000-4000-8000-000000000001';
        $id = new AccountId($uuid);
        $this->assertSame($uuid, $id->toString());
    }

    #[Test]
    public function acceptsUuidUppercase(): void
    {
        $uuid = '00000000-0000-4000-8000-000000000001';
        $id = new AccountId(strtoupper($uuid));
        $this->assertSame(strtoupper($uuid), $id->toString());
    }

    #[Test]
    public function emptyStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account ID cannot be empty');
        new AccountId('');
    }

    #[Test]
    public function invalidFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account ID must be a valid UUID');
        new AccountId('not-a-valid-uuid');
    }
}
