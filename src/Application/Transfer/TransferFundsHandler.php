<?php

declare(strict_types=1);

namespace App\Application\Transfer;

use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command_bus')]
final readonly class TransferFundsHandler
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private ExchangeRateProviderInterface $exchangeRates,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TransferFundsCommand $command): TransferFundsResult
    {
        $fromIdStr = $command->fromAccountId->toString();
        $toIdStr = $command->toAccountId->toString();
        $firstId = strcmp($fromIdStr, $toIdStr) <= 0 ? $command->fromAccountId : $command->toAccountId;
        $secondId = strcmp($fromIdStr, $toIdStr) <= 0 ? $command->toAccountId : $command->fromAccountId;
        $first = $this->accounts->lockForUpdate($firstId);
        $second = $this->accounts->lockForUpdate($secondId);
        $from = $first->id()->toString() === $fromIdStr ? $first : $second;
        $to = $second->id()->toString() === $toIdStr ? $second : $first;

        $fromCurrency = $from->balance()->getCurrency();
        $toCurrency = $to->balance()->getCurrency();
        $fromCode = $fromCurrency->getCode();
        $toCode = $toCurrency->getCode();
        $amountInFromCurrency = new Money((string) $command->amountMinor, $fromCurrency);

        if ($fromCode === $toCode) {
            $amountToCredit = $amountInFromCurrency;
        } else {
            $rate = $this->exchangeRates->getExchangeRate($fromCode, $toCode);
            $convertedMinor = (int) round((float) $amountInFromCurrency->getAmount() * $rate);
            $amountToCredit = new Money((string) $convertedMinor, new Currency($toCode));
        }

        $from->debit($amountInFromCurrency);
        $to->credit($amountToCredit);

        $this->accounts->save($from);
        $this->accounts->save($to);

        $transferId = $command->idempotencyKey;
        $this->logger->info('Transfer completed', [
            'transfer_id' => $transferId,
            'from' => $command->fromAccountId->toString(),
            'to' => $command->toAccountId->toString(),
            'amount_minor' => $command->amountMinor,
        ]);

        return new TransferFundsResult(
            transferId: $transferId,
            fromAccountId: $command->fromAccountId->toString(),
            toAccountId: $command->toAccountId->toString(),
            amountMinor: $command->amountMinor,
        );
    }
}
