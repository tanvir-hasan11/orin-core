<?php

declare(strict_types=1);

namespace Orin\Core;

final class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, mixed>  $files
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, string> $server
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $files = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $server = [],
        public array $attributes = [],
        public readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string) $key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        // Webhook signature verification needs the untouched body.
        $raw = '';
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $raw = (string) file_get_contents('php://input');
        }

        $body = $_POST;
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            body: $body,
            files: $_FILES,
            headers: $headers,
            cookies: $_COOKIE,
            server: $_SERVER,
            rawBody: $raw,
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('Content-Type', ''), 'application/json')
            || str_starts_with($this->path, '/api/')
            || str_starts_with($this->path, '/webhooks/');
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', $header, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }
}
