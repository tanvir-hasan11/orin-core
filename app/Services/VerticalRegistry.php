<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * Read-only access to the vertical prompt packs in config/verticals.php.
 */
final class VerticalRegistry
{
    /** @param array<string, array<string, mixed>> $verticals */
    public function __construct(private array $verticals)
    {
    }

    public function has(string $key): bool
    {
        return isset($this->verticals[$key]);
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        return $this->verticals[$key] ?? $this->verticals['generic'] ?? [
            'label' => 'business',
            'goal' => 'help the customer',
            'instructions' => 'Answer only from the business information you were given.',
            'lead_fields' => ['phone'],
            'first_question' => 'How can I help you today?',
            'stages' => ['new' => 'New', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'],
        ];
    }

    /** @return array<string, string> */
    public function stages(string $key): array
    {
        $stages = $this->get($key)['stages'] ?? [];

        return is_array($stages) ? $stages : [];
    }

    public function isValidStage(string $key, string $stage): bool
    {
        return array_key_exists($stage, $this->stages($key));
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->verticals);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->verticals;
    }
}
