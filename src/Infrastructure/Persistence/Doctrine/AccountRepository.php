<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Transfer\AccountRepositoryInterface;
use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Account\AccountNotFoundException;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountEntity;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/** Loads account with available balance (ledger − active holds); supports pessimistic lock. */
final readonly class AccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LedgerRepository $ledger,
        private HoldRepository $holds,
    ) {
    }

    /**
     * @throws AccountNotFoundException When account does not exist.
     */
    public function get(AccountId $id): Account
    {
        $entity = $this->em->find(AccountEntity::class, Uuid::fromString($id->toString()));
        if (null === $entity) {
            throw AccountNotFoundException::withId($id);
        }

        return $this->toAccount($entity);
    }

    /**
     * @throws AccountNotFoundException When account does not exist.
     */
    public function lockForUpdate(AccountId $id): Account
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(AccountEntity::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', Uuid::fromString($id->toString()));
        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        $entity = $query->getOneOrNullResult();
        if (!$entity instanceof AccountEntity) {
            throw AccountNotFoundException::withId($id);
        }

        return $this->toAccount($entity);
    }

    private function toAccount(AccountEntity $entity): Account
    {
        $currency = $entity->getCurrency() !== '' ? $entity->getCurrency() : 'USD';
        $accountIdString = $entity->getId()->toString();
        $balance = $this->ledger->getBalanceForAccount($accountIdString, $currency);
        $holds = $this->holds->getActiveHoldsSum($accountIdString, $currency);
        $available = $balance->subtract($holds);
        if ($available->isNegative()) {
            $available = new Money('0', $balance->getCurrency());
        }

        return new Account(
            new AccountId($accountIdString),
            $available,
        );
    }
}
