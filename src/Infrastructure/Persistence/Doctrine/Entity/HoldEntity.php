<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Transfer\HoldStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embedded;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/** Doctrine entity: hold (reservation) on an account; status Active/Captured/Released. */
#[ORM\Entity]
#[ORM\Table(name: 'holds')]
#[ORM\Index(name: 'holds_account_idx', columns: ['account_id'])]
#[ORM\Index(name: 'holds_account_status_idx', columns: ['account_id', 'status'])]
final class HoldEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid_string', unique: true)]
        private Uuid $id,
        #[ORM\Column(name: 'account_id', type: 'uuid_string')]
        private Uuid $accountId,
        #[Embedded]
        private Money $amount,
        #[ORM\Column(enumType: HoldStatus::class)]
        private HoldStatus $status,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $reason = null,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccountId(): Uuid
    {
        return $this->accountId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStatus(): HoldStatus
    {
        return $this->status;
    }

    public function setStatus(HoldStatus $status): void
    {
        $this->status = $status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
