<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Limits how often a client address may call the token refresh endpoint.
 *
 * That endpoint is answered by the security firewall, before any controller,
 * so the #[RateLimit] attribute used elsewhere would never run for it. This
 * listener sits between the router (which names the route) and the firewall.
 */
final class RefreshRateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $refreshLimiter,
    ) {
    }

    // Router: 32, firewall: 8.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('api_auth_refresh' !== $request->attributes->get('_route')) {
            return;
        }

        $limit = $this->refreshLimiter->create($request->getClientIp() ?? 'unknown')->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many requests.');
        }
    }
}
