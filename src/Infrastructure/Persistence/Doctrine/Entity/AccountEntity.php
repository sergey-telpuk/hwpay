<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Account\Currency;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account')]
final class AccountEntity
{
    #[ORM\Column(name: 'balance_minor', type: 'bigint')]
    private string $balanceMinor;

    public function __construct(#[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private string $id, int $balanceMinor, #[ORM\Column(type: 'string', length: 3)]
        private string $currency = Currency::DEFAULT_CODE)
    {
        $this->balanceMinor = (string) $balanceMinor;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBalanceMinor(): int
    {
        return (int) $this->balanceMinor;
    }

    public function setBalanceMinor(int $balanceMinor): void
    {
        $this->balanceMinor = (string) $balanceMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }
}
