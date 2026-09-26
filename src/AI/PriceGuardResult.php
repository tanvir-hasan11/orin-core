<?php

declare(strict_types=1);

namespace Orin\AI;

final class PriceGuardResult
{
    /**
     * @param float[] $mentioned  every currency figure found in the reply
     * @param float[] $unverified the subset of those with no catalog match
     */
    public function __construct(
        public readonly array $mentioned,
        public readonly array $unverified,
    ) {}

    public function passed(): bool
    {
        return $this->unverified === [];
    }
}
