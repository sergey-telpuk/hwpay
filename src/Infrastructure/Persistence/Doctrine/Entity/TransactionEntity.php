<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Transfer\TransactionStatus;
use App\Domain\Transfer\TransactionType;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embedded;
use Money\Money;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'transactions_created_idx', columns: ['created_at'])]
#[ORM\Index(name: 'transactions_status_idx', columns: ['status'])]
#[ORM\Index(name: 'transactions_type_idx', columns: ['type'])]
#[ORM\UniqueConstraint(name: 'transactions_external_id_unique', columns: ['external_id'])]
final class TransactionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid_string', unique: true)]
        private Uuid $id,
        #[ORM\Column(name: 'external_id', type: 'string', length: 255)]
        private string $externalId,
        #[ORM\Column(enumType: TransactionType::class)]
        private TransactionType $type,
        #[ORM\Column(enumType: TransactionStatus::class)]
        private TransactionStatus $status,
        #[ORM\Column(name: 'from_account_id', type: 'uuid_string')]
        private Uuid $fromAccountId,
        #[ORM\Column(name: 'to_account_id', type: 'uuid_string')]
        private Uuid $toAccountId,
        #[Embedded]
        private Money $amount,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        /** @var array<string, mixed> */
        #[ORM\Column(type: 'json')]
        private array $meta = [],
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): void
    {
        $this->status = $status;
    }

    public function getFromAccountId(): Uuid
    {
        return $this->fromAccountId;
    }

    public function getToAccountId(): Uuid
    {
        return $this->toAccountId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    /** @return array<string, mixed> */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
