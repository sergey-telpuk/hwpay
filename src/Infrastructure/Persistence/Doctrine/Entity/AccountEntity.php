<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Doctrine entity: account (owner, currency, type, status). Balance is derived from ledger minus holds. */
#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(name: 'accounts_owner_idx', columns: ['owner_type', 'owner_id'])]
#[ORM\Index(name: 'accounts_currency_idx', columns: ['currency'])]
final class AccountEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid_string', unique: true)]
        private Uuid $id,
        #[ORM\Column(name: 'owner_type', type: 'string', length: 20)]
        private string $ownerType,
        #[ORM\Column(name: 'owner_id', type: 'string', length: 36)]
        private string $ownerId,
        #[ORM\Column(type: 'string', length: 3)]
        private string $currency,
        #[ORM\Column(type: 'string', length: 20)]
        private string $type,
        #[ORM\Column(type: 'string', length: 20)]
        private string $status,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
