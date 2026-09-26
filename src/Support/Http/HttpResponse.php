<?php

declare(strict_types=1);

namespace Orin\Support\Http;

/**
 * Value object wrapping a raw HTTP response.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly mixed $json = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
