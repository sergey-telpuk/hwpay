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

final readonly class AccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function get(AccountId $id): Account
    {
        $entity = $this->em->find(AccountEntity::class, $id->toString());
        if (null === $entity) {
            throw AccountNotFoundException::withId($id);
        }

        return $this->toDomain($entity);
    }

    public function save(Account $account): void
    {
        $balanceMinor = (int) $account->balance()->getAmount();
        $currencyCode = $account->balance()->getCurrency()->getCode();
        $entity = $this->em->find(AccountEntity::class, $account->id()->toString());
        if (null !== $entity) {
            $entity->setBalanceMinor($balanceMinor);
            $entity->setCurrency($currencyCode);
        } else {
            $entity = new AccountEntity($account->id()->toString(), $balanceMinor, $currencyCode);
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function lockForUpdate(AccountId $id): Account
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(AccountEntity::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', $id->toString());
        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        $entity = $query->getOneOrNullResult();
        if (null === $entity) {
            throw AccountNotFoundException::withId($id);
        }

        return $this->toDomain($entity);
    }

    private function toDomain(AccountEntity $entity): Account
    {
        $balance = new Money((string) $entity->getBalanceMinor(), new Currency($entity->getCurrency()));

        return new Account(
            new AccountId($entity->getId()),
            $balance,
        );
    }
}
