<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * The result of stripping machine directives out of an AI reply.
 */
final class ReplyParseResult
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $stage = null,
        public readonly array $fields = [],
        public readonly bool $handoff = false,
        public readonly ?string $handoffReason = null,
    ) {
    }

    public function hasAnything(): bool
    {
        return $this->stage !== null || $this->fields !== [] || $this->handoff;
    }
}
