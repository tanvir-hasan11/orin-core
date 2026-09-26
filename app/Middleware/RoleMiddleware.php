<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Support\Container;

/**
 * Route guard for roles, e.g. middleware "role:admin".
 * super_admin implicitly satisfies both merchant and admin requirements.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private Container $container)
    {
    }

    public function name(): string
    {
        return 'role';
    }

    public function handle(Request $request, callable $next): Response
    {
        $required = $request->attributes['required_role'] ?? null;
        $role = (string) ($request->attributes['user_role'] ?? 'guest');

        if (!is_string($required) || $required === '') {
            return $next($request);
        }

        $allowed = [$required];
        if ($required === 'admin') {
            $allowed[] = 'super_admin';
        }

        if ($role === 'super_admin' || in_array($role, $allowed, true)) {
            return $next($request);
        }

        return $request->isJson()
            ? Response::json(['error' => 'Forbidden.'], 403)
            : Response::html('<h1>403</h1><p>You do not have access to this area.</p>', 403);
    }
}
