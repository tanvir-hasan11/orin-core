<?php

declare(strict_types=1);

namespace Orin\AI;

/** Normalised response from any AI provider. */
final class ProviderResponse
{
    public function __construct(
        public readonly string  $text,
        public readonly string  $provider,
        public readonly string  $model,
        public readonly int     $tokensIn  = 0,
        public readonly int     $tokensOut = 0,
        public readonly ?array  $toolCalls = null,
        public readonly ?float  $confidence = null,
        public readonly array   $raw = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }
}
