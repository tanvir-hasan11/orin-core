<?php

declare(strict_types=1);

use Orin\Support\Container;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /**
     * @param array<string, mixed> $tree
     */
    function config(array $tree, string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $tree;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $append = ''): string
    {
        $base = defined('ORIN_BASE') ? ORIN_BASE : dirname(__DIR__, 2);

        return $append === '' ? $base : $base . '/' . ltrim($append, '/');
    }
}

if (!function_exists('old')) {
    /**
     * @param array<string, mixed> $flash
     */
    function old(array $flash, string $key, string $default = ''): string
    {
        $values = $flash['old'] ?? [];
        $value = is_array($values) ? ($values[$key] ?? $default) : $default;

        return (string) $value;
    }
}

if (!function_exists('app')) {
    function app(?string $service = null): mixed
    {
        $container = $GLOBALS['__orin_container'] ?? null;
        if (!$container instanceof Container) {
            throw new RuntimeException('Application container is not booted.');
        }

        return $service === null ? $container : $container->get($service);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view. The optional $layout overrides the default layout.
     *
     * @param array<string, mixed> $data
     */
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return app('view')->render($template, $data, $layout);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return (string) app('csrf')->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('current_user')) {
    /** @return array<string, mixed>|null */
    function current_user(): ?array
    {
        $session = app('session');
        $id = $session->get('user_id');
        if ($id === null) {
            return null;
        }

        return [
            'id' => (int) $id,
            'role' => (string) $session->get('user_role', 'merchant'),
            'email' => (string) $session->get('user_email', ''),
            'name' => (string) $session->get('user_name', ''),
        ];
    }
}

if (!function_exists('merchant_sidebar')) {
    function merchant_sidebar(): string
    {
        $links = [
            '/merchant/dashboard' => 'Dashboard',
            '/merchant/api-keys' => 'API Keys',
            '/merchant/usage' => 'Usage',
            '/merchant/providers' => 'Providers',
            '/merchant/billing' => 'Billing',
            '/merchant/settings' => 'Settings',
        ];

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $html = '';
        foreach ($links as $href => $label) {
            $active = str_starts_with($path, $href) ? ' style="background:#1e293b;color:#fff"' : '';
            $html .= '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
        }

        return $html;
    }
}

if (!function_exists('admin_sidebar')) {
    function admin_sidebar(): string
    {
        $links = [
            '/admin/dashboard' => 'Dashboard',
            '/admin/merchants' => 'Merchants',
            '/admin/plans' => 'Plans',
            '/admin/subscriptions' => 'Subscriptions',
            '/admin/providers' => 'Providers',
            '/admin/settings' => 'Settings',
            '/admin/audit' => 'Audit',
        ];

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $html = '';
        foreach ($links as $href => $label) {
            $active = str_starts_with($path, $href) ? ' style="background:#1e293b;color:#fff"' : '';
            $html .= '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
        }

        return $html;
    }
}
