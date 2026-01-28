<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Transfer\TransferFundsCommand;
use App\Application\Transfer\TransferFundsResult;
use App\Domain\Account\AccountId;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\InsufficientBalanceException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
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

#[AsController]
final class TransferController
{
    use HandleTrait;

    private const int IDEMPOTENCY_TTL = 86400;
    private const string CACHE_KEY_PREFIX = 'transfer_idempotency_';

    public function __construct(
        MessageBusInterface $commandBus,
        private ValidatorInterface $validator,
        private AdapterInterface $cache,
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

        $idempotencyKey = $payload['idempotency_key'] ?? '';
        $cacheKey = self::CACHE_KEY_PREFIX.hash('sha256', $idempotencyKey);
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $cached = $cacheItem->get();

            return new JsonResponse($cached['body'], $cached['status']);
        }

        try {
            $command = new TransferFundsCommand(
                fromAccountId: new AccountId($payload['from_account_id']),
                toAccountId: new AccountId($payload['to_account_id']),
                amountMinor: (int) $payload['amount_minor'],
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
            throw $e;
        } catch (AccountNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InsufficientBalanceException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $body = [
            'transfer_id' => $result->transferId,
            'from_account_id' => $result->fromAccountId,
            'to_account_id' => $result->toAccountId,
            'amount_minor' => $result->amountMinor,
        ];
        $response = new JsonResponse($body, Response::HTTP_OK);
        $cacheItem->set(['body' => $body, 'status' => Response::HTTP_OK]);
        $cacheItem->expiresAfter(self::IDEMPOTENCY_TTL);
        $this->cache->save($cacheItem);

        return $response;
    }

    private function parseBody(Request $request): ?array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return null;
        }
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

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
            $errors[$v->getPropertyPath()] = $v->getMessage();
        }

        return $errors;
    }
}
