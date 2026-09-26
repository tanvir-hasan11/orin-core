<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * An agent reply after the machine directives have been pulled out of it.
 */
final class ReplyParseResult
{
    /**
     * @param array<string, string> $fields  lead fields the agent learned
     * @param array<int, array{kind: string, payload: array<string, string>}> $actions
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $stage = null,
        public readonly array $fields = [],
        public readonly bool $handoff = false,
        public readonly ?string $handoffReason = null,
        public readonly array $actions = [],
    ) {
    }

    public function hasAnything(): bool
    {
        return $this->stage !== null || $this->fields !== [] || $this->handoff || $this->actions !== [];
    }

    public function hasActions(): bool
    {
        return $this->actions !== [];
    }
}
