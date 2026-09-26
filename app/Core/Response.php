<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * Simple HTTP response value object.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'] + $headers
        );
    }

    /** @param array<string, string> $headers */
    public static function redirect(string $to, int $status = 302, array $headers = []): self
    {
        return new self('', $status, ['Location' => $to] + $headers);
    }

    public static function notFound(): self
    {
        return self::html('<h1>404</h1><p>Page not found.</p>', 404);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
