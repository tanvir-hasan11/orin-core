<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * Per-session CSRF token handling.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function check(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
