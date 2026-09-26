<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\RateLimiter;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Support\Container;

/**
 * Global per-IP throttle. Route-level throttling lands in Phase 4.
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
