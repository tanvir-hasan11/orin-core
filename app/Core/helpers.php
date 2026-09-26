<?php

declare(strict_types=1);

/**
 * Global helpers. Autoloaded via composer "files".
 */

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /**
     * Read a dotted config value from the given array tree.
     *
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
     * Fetch a flashed form value.
     *
     * @param array<string, mixed> $flash
     */
    function old(array $flash, string $key, string $default = ''): string
    {
        $values = $flash['old'] ?? [];
        $value = is_array($values) ? ($values[$key] ?? $default) : $default;

        return (string) $value;
    }
}
