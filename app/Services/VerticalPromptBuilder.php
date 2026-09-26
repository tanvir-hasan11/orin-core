<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * The vertical rules block: what kind of business this is and how that kind of
 * business is run. AgentPromptBuilder wraps it with everything the individual
 * agent knows and is allowed to do.
 */
final class VerticalPromptBuilder
{
    public function __construct(private VerticalRegistry $verticals)
    {
    }

    public function block(string $type): string
    {
        $vertical = $this->verticals->get($type);

        $lines = [];
        $lines[] = 'You are working with a ' . (string) $vertical['label'] . '.';
        $lines[] = 'Your goal is to ' . (string) $vertical['goal'] . '.';
        $lines[] = (string) $vertical['instructions'];

        return implode("\n", $lines);
    }
}
