<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Application\Transfer\IdempotencyStoreInterface;
use App\Application\Transfer\TransactionRepositoryInterface;
use App\Application\Transfer\TransferFundsResult;
use Money\Currency;
use Money\Money;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/** Idempotency: Redis (cache.app) first, then DB via TransactionRepository; TTL 24h. */
final readonly class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    private const string CACHE_KEY_PREFIX = 'transfer_idempotency_';
    private const int TTL_SECONDS = 86400;

    public function __construct(
        private AdapterInterface $cache,
        private TransactionRepositoryInterface $transactions,
    ) {
    }

    public function get(string $idempotencyKey): ?TransferFundsResult
    {
        $key = self::CACHE_KEY_PREFIX . hash('sha256', $idempotencyKey);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $cached = $item->get();
            if (is_array($cached)) {
                return $this->deserialize($cached);
            }
        }

        $fromDb = $this->transactions->findOneByExternalId($idempotencyKey);
        if ($fromDb !== null) {
            $this->set($idempotencyKey, $fromDb);
        }

        return $fromDb;
    }

    public function set(string $idempotencyKey, TransferFundsResult $result): void
    {
        $key = self::CACHE_KEY_PREFIX . hash('sha256', $idempotencyKey);
        $item = $this->cache->getItem($key);
        $item->set([
            'transfer_id' => $result->transferId,
            'from_account_id' => $result->fromAccountId,
            'to_account_id' => $result->toAccountId,
            'amount_minor' => (int) $result->amount->getAmount(),
            'currency' => $result->amount->getCurrency()->getCode(),
        ]);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);
    }

    /**
     * @param array{transfer_id?: string, from_account_id?: string, to_account_id?: string, amount_minor?: int, currency?: string} $cached
     */
    private function deserialize(array $cached): TransferFundsResult
    {
        $amount = new Money(
            (string) ($cached['amount_minor'] ?? 0),
            new Currency($cached['currency'] ?? 'USD'),
        );

        return new TransferFundsResult(
            transferId: $cached['transfer_id'] ?? '',
            fromAccountId: $cached['from_account_id'] ?? '',
            toAccountId: $cached['to_account_id'] ?? '',
            amount: $amount,
        );
    }
}
