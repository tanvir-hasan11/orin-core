<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Session;
use Orin\Support\Container;

/**
 * Requires an authenticated user (session based).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private Session $session;

    public function __construct(Container $container)
    {
        $this->session = $container->get('session');
    }

    public function name(): string
    {
        return 'auth';
    }

    public function handle(Request $request, callable $next): Response
    {
        $userId = $this->session->get('user_id');

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return $request->isJson()
                ? Response::json(['error' => 'Unauthenticated.'], 401)
                : Response::redirect('/login');
        }

        $request->attributes['user_id'] = (int) $userId;
        $request->attributes['user_role'] = (string) $this->session->get('user_role', 'merchant');

        return $next($request);
    }
}
