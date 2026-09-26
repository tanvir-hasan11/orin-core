<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Immutable response returned by every provider.
 */
final class ProviderResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $latencyMs = 0,
        public readonly array $raw = [],
    ) {
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
