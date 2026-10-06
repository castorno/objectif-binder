<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Gives authentication failures the same {"error": "..."} body as every other
 * API error, instead of the JWT bundle's own {"code", "message"} format.
 *
 * The messages are fixed strings on purpose: they never say whether an e-mail
 * exists, nor why exactly a token was rejected.
 */
final class JwtFailureResponseListener
{
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onInvalidCredentials(AuthenticationFailureEvent $event): void
    {
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            // Not a 401: the credentials were not even looked at.
            $event->setResponse(new JsonResponse(
                ['error' => 'Too many login attempts. Try again later.'],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
            ));

            return;
        }

        $this->respond($event, 'Invalid credentials.');
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onMissingToken(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'Authentication required.');
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpiredToken(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'Expired token.');
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalidToken(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'Invalid token.');
    }

    private function respond(AuthenticationFailureEvent $event, string $message): void
    {
        $event->setResponse(new JsonResponse(
            ['error' => $message],
            JsonResponse::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        ));
    }
}
