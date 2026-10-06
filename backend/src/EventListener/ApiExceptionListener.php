<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Ensures every exception on an /api/* route produces a JSON body instead of
 * Symfony's default HTML (debug) error page.
 */
#[AsEventListener(event: 'kernel.exception')]
final class ApiExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        // Headers the exception carries are part of the answer: Retry-After
        // on a 429, Allow on a 405.
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        // A request body that failed validation: say which field is at fault,
        // so a form can show each message next to its input.
        $validationFailure = $exception->getPrevious();
        if ($validationFailure instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'Validation failed.',
                'violations' => $this->messagesByField($validationFailure->getViolations()),
            ], $statusCode, $headers));

            return;
        }

        $message = $statusCode < 500 || $this->debug
            ? $exception->getMessage()
            : 'Internal Server Error';

        $event->setResponse(new JsonResponse(['error' => $message], $statusCode, $headers));
    }

    /**
     * @return array<string, list<string>>
     */
    private function messagesByField(ConstraintViolationListInterface $violations): array
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[$violation->getPropertyPath()][] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
