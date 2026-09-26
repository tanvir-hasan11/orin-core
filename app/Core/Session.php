<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * Server-side-ish PHP session wrapper with sane cookie defaults.
 */
final class Session
{
    private bool $started = false;

    public function __construct(
        private string $name = 'orin_session',
        private int $lifetime = 7200,
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);

        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->started = false;
    }

    /** @param array<string, mixed> $value */
    public function flash(string $key, array $value): void
    {
        $this->set('__flash_' . $key, $value);
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('__flash_' . $key, $default);
        $this->forget('__flash_' . $key);

        return $value;
    }
}
