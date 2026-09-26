<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\RateLimiter;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Support\Container;

/**
 * Per-IP throttle for browser traffic.
 *
 * Webhook delivery is not throttled here: Meta retries are signed and the
 * processor already de-duplicates by message id, so a rate limit would only
 * cause Meta to queue messages we are about to accept anyway.
 */
final class ThrottleMiddleware implements MiddlewareInterface
{
    private RateLimiter $limiter;

    public function __construct(Container $container)
    {
        $this->limiter = $container->get('rate_limiter');
    }

    public function name(): string
    {
        return 'throttle';
    }

    public function handle(Request $request, callable $next): Response
    {
        if (str_starts_with($request->path, '/webhooks/')) {
            return $next($request);
        }

        $key = 'ip:' . $request->ip();
        $max = 120;
        $decay = 60;

        if ($this->limiter->tooManyAttempts($key, $max, $decay)) {
            return $request->isJson()
                ? Response::json(['error' => 'Too many requests.'], 429)
                : Response::html('<h1>429</h1><p>Too many requests. Slow down.</p>', 429);
        }

        $this->limiter->hit($key, $decay);

        return $next($request);
    }
}
