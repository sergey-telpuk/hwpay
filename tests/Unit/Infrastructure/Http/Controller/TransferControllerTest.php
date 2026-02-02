<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Controller;

use App\Application\Transfer\TransferFundsCommand;
use App\Application\Transfer\TransferFundsResult;
use App\Domain\Account\AccountId;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\InsufficientBalanceException;
use App\Infrastructure\Http\Controller\TransferController;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(TransferController::class)]
final class TransferControllerTest extends TestCase
{
    private MockObject&MessageBusInterface $commandBus;

    private MockObject&ValidatorInterface $validator;

    private TransferController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->controller = new TransferController($this->commandBus, $this->validator);
    }

    #[Test]
    public function invokeEmptyBodyReturnsInvalidJson(): void
    {
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], '');
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string, error: string} $data */
        $this->assertSame('INVALID_JSON', $data['code']);
        $this->assertSame('Invalid JSON', $data['error']);
    }

    #[Test]
    public function invokeInvalidJsonReturnsInvalidJson(): void
    {
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], 'not json');
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('INVALID_JSON', $data['code']);
    }

    #[Test]
    public function invokeJsonWithNonStringKeysReturnsInvalidJson(): void
    {
        $content = json_encode([0 => 'value']);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('INVALID_JSON', $data['code']);
    }

    #[Test]
    public function invokeValidationErrorsReturns422(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);

        $violation = new ConstraintViolation('must not be blank', null, [], null, 'from_account_id', '');
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string, errors: array<string, string>} $data */
        $this->assertSame('VALIDATION_FAILED', $data['code']);
        $this->assertArrayHasKey('from_account_id', $data['errors']);
    }

    #[Test]
    public function invokeInvalidPayloadTypeReturnsInvalidPayload(): void
    {
        $payload = [
            'from_account_id' => ['uuid'],
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('INVALID_PAYLOAD', $data['code']);
    }

    #[Test]
    public function invokeAccountNotFoundReturns404(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $notFound = new AccountNotFoundException('00000000-0000-4000-8000-000000000001');
        $this->commandBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope(new TransferFundsCommand(
                fromAccountId: new AccountId($payload['from_account_id']),
                toAccountId: new AccountId($payload['to_account_id']),
                amountMinor: (int) $payload['amount_minor'],
                idempotencyKey: $payload['idempotency_key'],
            )),
            ['handler' => $notFound],
        ));

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('ACCOUNT_NOT_FOUND', $data['code']);
    }

    #[Test]
    public function invokeInsufficientBalanceReturns422(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $insufficient = InsufficientBalanceException::forAccount(
            new AccountId('00000000-0000-4000-8000-000000000001'),
            new Money('50', new Currency('USD')),
            new Money('100', new Currency('USD')),
        );
        $this->commandBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope(new TransferFundsCommand(
                fromAccountId: new AccountId($payload['from_account_id']),
                toAccountId: new AccountId($payload['to_account_id']),
                amountMinor: (int) $payload['amount_minor'],
                idempotencyKey: $payload['idempotency_key'],
            )),
            ['handler' => $insufficient],
        ));

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('INSUFFICIENT_BALANCE', $data['code']);
    }

    #[Test]
    public function invokeInvalidArgumentReturns400(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $this->commandBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope(new TransferFundsCommand(
                fromAccountId: new AccountId($payload['from_account_id']),
                toAccountId: new AccountId($payload['to_account_id']),
                amountMinor: (int) $payload['amount_minor'],
                idempotencyKey: $payload['idempotency_key'],
            )),
            ['handler' => new InvalidArgumentException('Invalid UUID')],
        ));

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{code: string} $data */
        $this->assertSame('INVALID_ARGUMENT', $data['code']);
    }

    #[Test]
    public function invokeHandlerFailedWithUnknownExceptionRethrows(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $runtime = new \RuntimeException('Internal server error');
        $this->commandBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope(new TransferFundsCommand(
                fromAccountId: new AccountId($payload['from_account_id']),
                toAccountId: new AccountId($payload['to_account_id']),
                amountMinor: (int) $payload['amount_minor'],
                idempotencyKey: $payload['idempotency_key'],
            )),
            ['handler' => $runtime],
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Internal server error');
        ($this->controller)($request);
    }

    #[Test]
    public function invokeDirectThrowableRethrows(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $this->commandBus->method('dispatch')->willThrowException(new \RuntimeException('Direct error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Direct error');
        ($this->controller)($request);
    }

    #[Test]
    public function invokeSuccessReturns200WithResult(): void
    {
        $payload = [
            'from_account_id' => '00000000-0000-4000-8000-000000000001',
            'to_account_id' => '00000000-0000-4000-8000-000000000002',
            'amount_minor' => '100',
            'idempotency_key' => 'key-1',
        ];
        $content = json_encode($payload);
        $this->assertNotFalse($content);
        $request = Request::create('/api/transfer', 'POST', [], [], [], [], $content);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([]));

        $result = new TransferFundsResult(
            'tx-123',
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            new Money('100', new Currency('USD')),
        );
        $this->commandBus->method('dispatch')->willReturnCallback(
            static fn(object $message): Envelope => new Envelope($message, [new HandledStamp($result, 'handler')]),
        );

        $response = ($this->controller)($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($data);
        /** @var array{transfer_id: string, from_account_id: string, to_account_id: string, amount_minor: int, currency: string} $data */
        $this->assertSame('tx-123', $data['transfer_id']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $data['from_account_id']);
        $this->assertSame('00000000-0000-4000-8000-000000000002', $data['to_account_id']);
        $this->assertSame(100, $data['amount_minor']);
        $this->assertSame('USD', $data['currency']);
    }
}
