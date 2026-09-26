<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Every AI provider adapter implements this.
 *
 * A provider MUST report isConfigured() = false when its API key is
 * missing or empty. The engine relies on this to skip dead entries in
 * the failover chain instead of burning a request on them.
 */
interface ProviderInterface
{
    /** Short name used in logs and the messages table. e.g. "gemini" */
    public function name(): string;

    /** True when this provider has everything it needs to make a call. */
    public function isConfigured(): bool;

    /**
     * Send a chat completion request.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array{model?: string, temperature?: float, max_tokens?: int, tools?: array} $options
     *
     * @throws RateLimitException     on HTTP 429
     * @throws ProviderException      on any other provider failure
     */
    public function chat(array $messages, array $options = []): ProviderResponse;
}
