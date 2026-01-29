<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embedded;
use Money\Money;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'fx_transactions')]
#[ORM\UniqueConstraint(name: 'fx_transactions_transaction_id_unique', columns: ['transaction_id'])]
final class FxTransactionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid_string', unique: true)]
        private Uuid $id,
        #[ORM\Column(name: 'transaction_id', type: 'uuid_string')]
        private Uuid $transactionId,
        #[Embedded]
        private Money $baseAmount,
        #[Embedded]
        private Money $quoteAmount,
        #[ORM\Column(type: 'decimal', precision: 18, scale: 10)]
        private string $rate,
        #[ORM\Column(type: 'decimal', precision: 18, scale: 10)]
        private string $spread,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTransactionId(): Uuid
    {
        return $this->transactionId;
    }

    public function getBaseAmount(): Money
    {
        return $this->baseAmount;
    }

    public function getQuoteAmount(): Money
    {
        return $this->quoteAmount;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function getSpread(): string
    {
        return $this->spread;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
