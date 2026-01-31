<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Transfer\LedgerSide;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embedded;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/** Doctrine entity: single ledger entry (debit or credit) for an account in a transaction. */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(name: 'ledger_tx_idx', columns: ['transaction_id'])]
#[ORM\Index(name: 'ledger_account_time_idx', columns: ['account_id', 'created_at'])]
#[ORM\Index(name: 'ledger_account_id_idx', columns: ['account_id', 'id'])]
final class LedgerEntryEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid_string', unique: true)]
        private Uuid $id,
        #[ORM\Column(name: 'transaction_id', type: 'uuid_string')]
        private Uuid $transactionId,
        #[ORM\Column(name: 'account_id', type: 'uuid_string')]
        private Uuid $accountId,
        #[ORM\Column(enumType: LedgerSide::class)]
        private LedgerSide $side,
        #[Embedded]
        private Money $amount,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
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

    public function getAccountId(): Uuid
    {
        return $this->accountId;
    }

    public function getSide(): LedgerSide
    {
        return $this->side;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
