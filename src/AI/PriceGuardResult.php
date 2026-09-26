<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Outcome of a price guard inspection.
 */
final class PriceGuardResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $estimatedInputTokens,
        public readonly int $estimatedOutputTokens,
        public readonly float $estimatedCostUsd,
        public readonly ?string $reason = null,
    ) {
    }

    public static function block(int $input, int $output, float $cost, string $reason): self
    {
        return new self(false, $input, $output, $cost, $reason);
    }
}
