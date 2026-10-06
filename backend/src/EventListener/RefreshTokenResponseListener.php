<?php

declare(strict_types=1);

namespace App\EventListener;

use Gesdinet\JWTRefreshTokenBundle\Event\RefreshAuthenticationFailureEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Aligns the refresh token bundle's answers with the rest of the API, and
 * keeps them from saying more than a client needs to know.
 */
final class RefreshTokenResponseListener
{
    /**
     * One message whether the token is missing, unknown, expired or already
     * used: the difference is of no use to a client, only to someone probing.
     */
    #[AsEventListener(event: 'gesdinet.refresh_token_failure')]
    public function onRefreshFailure(RefreshAuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();
        $tooManyRequests = Response::HTTP_TOO_MANY_REQUESTS === $response->getStatusCode();

        $response->setContent(json_encode([
            'error' => $tooManyRequests ? 'Too many requests.' : 'Invalid or expired refresh token.',
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * Runs after the bundle has deleted the token and prepared the cookie
     * removal. Logging out always succeeds the same way, with or without a
     * live session: there is nothing to report, and nothing to learn from it.
     */
    #[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.api', priority: -64)]
    public function onLogout(LogoutEvent $event): void
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);

        foreach ($event->getResponse()?->headers->getCookies() ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event->setResponse($response);
    }
}
