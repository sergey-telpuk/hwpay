<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Transfer\TransferFundsCommand;
use App\Application\Transfer\TransferFundsResult;
use App\Domain\Account\AccountId;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\InsufficientBalanceException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** POST /api/transfer: validates payload, dispatches TransferFundsCommand, returns JSON or error. */
#[AsController]
final class TransferController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly ValidatorInterface $validator,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/api/transfer', name: 'api_transfer', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): Response
    {
        $payload = $this->parseBody($request);
        if (null === $payload) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validatePayload($payload);
        if ([] !== $errors) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idempotencyKey = isset($payload['idempotency_key']) && is_string($payload['idempotency_key'])
            ? $payload['idempotency_key'] : '';

        $fromId = $payload['from_account_id'] ?? '';
        $toId = $payload['to_account_id'] ?? '';
        $amountMinor = $payload['amount_minor'] ?? 0;
        if (!is_string($fromId) || !is_string($toId) || !is_numeric($amountMinor)) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $command = new TransferFundsCommand(
                fromAccountId: new AccountId($fromId),
                toAccountId: new AccountId($toId),
                amountMinor: (int) $amountMinor,
                idempotencyKey: $idempotencyKey,
            );
            /** @var TransferFundsResult $result */
            $result = $this->handle($command);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof AccountNotFoundException) {
                return new JsonResponse(['error' => $previous->getMessage()], Response::HTTP_NOT_FOUND);
            }
            if ($previous instanceof InsufficientBalanceException) {
                return new JsonResponse(['error' => $previous->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if ($previous instanceof InvalidArgumentException) {
                return new JsonResponse(['error' => $previous->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            throw $e;
        } catch (AccountNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InsufficientBalanceException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $body = [
            'transfer_id' => $result->transferId,
            'from_account_id' => $result->fromAccountId,
            'to_account_id' => $result->toAccountId,
            'amount_minor' => (int) $result->amount->getAmount(),
        ];

        return new JsonResponse($body, Response::HTTP_OK);
    }

    /** @return array<string, mixed>|null */
    private function parseBody(Request $request): ?array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return null;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (!\is_string($k)) {
                return null;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validatePayload(array $payload): array
    {
        $constraints = new Assert\Collection([
            'from_account_id' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(min: 1, max: 36)],
            'to_account_id' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(min: 1, max: 36)],
            'amount_minor' => [new Assert\NotBlank(), new Assert\Type('numeric'), new Assert\Positive()],
            'idempotency_key' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Length(min: 1, max: 128),
            ],
        ]);
        $violations = $this->validator->validate($payload, $constraints);
        $errors = [];
        foreach ($violations as $v) {
            $errors[$v->getPropertyPath()] = (string) $v->getMessage();
        }

        return $errors;
    }
}
