<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Contract every AI provider must implement.
 */
interface ProviderInterface
{
    /**
     * Human-readable provider name (e.g. "openai").
     */
    public function name(): string;

    /**
     * Whether the provider is usable (i.e. has credentials configured).
     */
    public function isConfigured(): bool;

    /**
     * Send a prompt to the provider and return the response.
     *
     * @param array<string, mixed> $options
     */
    public function complete(string $prompt, array $options = []): ProviderResponse;
}
