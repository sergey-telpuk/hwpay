<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Transfer\TransactionRepositoryInterface;
use App\Application\Transfer\TransferFundsResult;
use App\Domain\Transfer\TransactionStatus;
use App\Infrastructure\Persistence\Doctrine\Entity\TransactionEntity;
use Doctrine\ORM\EntityManagerInterface;

/** Finds completed transactions by external_id (idempotency key) and maps to TransferFundsResult. */
final readonly class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findOneByExternalId(string $externalId): ?TransferFundsResult
    {
        /** @var TransactionEntity|null $entity */
        $entity = $this->em->getRepository(TransactionEntity::class)
            ->findOneBy(['externalId' => $externalId]);

        if ($entity === null || $entity->getStatus() !== TransactionStatus::Completed) {
            return null;
        }

        return new TransferFundsResult(
            transferId: $entity->getId()->toRfc4122(),
            fromAccountId: $entity->getFromAccountId()->toRfc4122(),
            toAccountId: $entity->getToAccountId()->toRfc4122(),
            amount: $entity->getAmount(),
        );
    }
}
