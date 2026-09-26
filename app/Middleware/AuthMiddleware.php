<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Session;
use Orin\Support\Container;

final class AuthMiddleware implements MiddlewareInterface
{
    private Session $session;
    private Database $db;

    public function __construct(Container $container)
    {
        $this->session = $container->get('session');
        $this->db = $container->get('db');
    }

    public function name(): string
    {
        return 'auth';
    }

    public function handle(Request $request, callable $next): Response
    {
        $userId = $this->session->get('user_id');

        if ($userId === null || !is_numeric($userId)) {
            return $request->isJson()
                ? Response::json(['error' => 'Unauthenticated.'], 401)
                : Response::redirect('/login');
        }

        $user = $this->db->first(
            'SELECT id, role, email, name, status FROM users WHERE id = :id LIMIT 1',
            ['id' => (int) $userId]
        );

        if ($user === null || ($user['status'] ?? '') !== 'active') {
            $this->session->destroy();

            return $request->isJson()
                ? Response::json(['error' => 'Unauthenticated.'], 401)
                : Response::redirect('/login');
        }

        $request->attributes['user_id'] = (int) $user['id'];
        $request->attributes['user_role'] = (string) $user['role'];
        $request->attributes['user'] = $user;

        return $next($request);
    }
}
