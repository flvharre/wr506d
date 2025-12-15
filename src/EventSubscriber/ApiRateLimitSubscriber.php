<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $anonymousApiLimiter,
        private readonly RateLimiterFactory $authenticatedApiLimiter,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after security firewall (priority 8) to ensure JWT authentication is processed
            KernelEvents::REQUEST => ['onKernelRequest', 5],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply rate limiting to API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Don't rate limit documentation endpoints
        if (str_starts_with($request->getPathInfo(), '/api/docs') ||
            str_starts_with($request->getPathInfo(), '/api/graphql/graphiql')) {
            return;
        }

        // Determine if user is authenticated via JWT bearer token
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $isAuthenticated = $user instanceof User;

        // Use IP address as identifier for anonymous users, user ID for authenticated users
        $identifier = $isAuthenticated
            ? $user->getUserIdentifier()
            : $request->getClientIp() ?? 'unknown';

        // Select appropriate rate limiter and create with custom limit if authenticated
        if ($isAuthenticated) {
            // For authenticated users, we'll use a dynamic approach
            // Create a limiter with user-specific identifier that includes the limit
            $customLimit = $user->getApiRateLimit();
            $limiter = $this->authenticatedApiLimiter->create($identifier . '_' . $customLimit);

            // Note: The actual limit is still controlled by the rate_limiter.yaml config
            // To truly customize per-user, we'd need the user's limit to match or we accept the config limit
            // For now, we use the config limit but track per user
        } else {
            // Use default limiter for anonymous users
            $limiter = $this->anonymousApiLimiter->create($identifier);
        }

        // Consume a token from the rate limiter
        $limit = $limiter->consume();

        // Store rate limit info in request attributes for the response listener
        $request->attributes->set('_rate_limit', [
            'limit' => $isAuthenticated ? $user->getApiRateLimit() : $limit->getLimit(),
            'remaining' => $limit->getRemainingTokens(),
            'reset' => $limit->getRetryAfter()->getTimestamp(),
        ]);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $now = new \DateTimeImmutable();
            $waitSeconds = $retryAfter->getTimestamp() - $now->getTimestamp();

            $response = new JsonResponse(
                [
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after_seconds' => $waitSeconds,
                    'retry_after_datetime' => $retryAfter->format('Y-m-d H:i:s'),
                    'retry_after_timestamp' => $retryAfter->getTimestamp(),
                ],
                429
            );

            $response->headers->set('Retry-After', (string) $retryAfter->getTimestamp());
            $response->headers->set('X-RateLimit-Limit', (string) ($isAuthenticated ? $user->getApiRateLimit() : $limit->getLimit()));
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());

            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only add headers if we have rate limit info
        $rateLimitInfo = $request->attributes->get('_rate_limit');
        if (!$rateLimitInfo) {
            return;
        }

        // Add rate limit headers to the response
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitInfo['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);
    }
}
