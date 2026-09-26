<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\Csrf;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Support\Container;

/**
 * Verifies the CSRF token on state-changing browser requests.
 * API routes authenticate with a bearer key, webhooks with an HMAC signature.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private Csrf $csrf;

    public function __construct(Container $container)
    {
        $this->csrf = $container->get('csrf');
    }

    public function name(): string
    {
        return 'csrf';
    }

    public function handle(Request $request, callable $next): Response
    {
        $safe = in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true);
        $exempt = str_starts_with($request->path, '/api/') || str_starts_with($request->path, '/webhooks/');

        if ($safe || $exempt) {
            return $next($request);
        }

        $token = $request->input('_token');
        if (!is_string($token)) {
            $token = $request->header('X-CSRF-Token');
        }

        if (!$this->csrf->check(is_string($token) ? $token : null)) {
            return Response::html('<h1>419</h1><p>CSRF token mismatch.</p>', 419);
        }

        return $next($request);
    }
}
