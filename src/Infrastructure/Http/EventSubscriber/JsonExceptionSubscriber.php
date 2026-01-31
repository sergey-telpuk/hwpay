<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns JSON error responses for API requests and when client accepts JSON.
 * Ensures all errors (including 500) are returned as JSON for /api/* or Accept: application/json.
 */
final class JsonExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->wantsJson($request)) {
            return;
        }

        $throwable = $event->getThrowable();
        $statusCode = $throwable instanceof HttpException
            ? $throwable->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $payload = [
            'error' => $throwable->getMessage(),
        ];

        if ('dev' === $this->environment && $statusCode >= 500) {
            $payload['detail'] = $throwable::class . ': ' . $throwable->getMessage();
            $payload['file'] = $throwable->getFile();
            $payload['line'] = $throwable->getLine();
        }

        $event->setResponse(new JsonResponse($payload, $statusCode));
    }

    private function wantsJson(Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        return $request->getPreferredFormat() === 'json'
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }
}
