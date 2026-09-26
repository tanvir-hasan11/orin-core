<?php

declare(strict_types=1);

namespace Orin\AI;

use Orin\AI\Providers\GeminiProvider;
use Orin\AI\Providers\OpenAIProvider;
use Orin\AI\Providers\OpenRouterProvider;

/**
 * The failover engine.
 *
 * Three rules it lives by:
 *
 *  1. A provider with no API key is NEVER in the chain. Silently skipping
 *     it is the whole point — the old engine kept a dead "openai" entry
 *     and burned a request on it every single time before giving up.
 *
 *  2. A 429 moves to the next provider immediately. Retrying the same
 *     rate-limited provider a second later is wasted latency.
 *
 *  3. A transient 5xx is retried ONCE on the same provider with backoff,
 *     then the chain moves on.
 *
 * If the chain is empty or every provider fails, the caller gets a loud
 * exception with the full attempt log — never a silent no-reply.
 */
final class ResilientAIEngine
{
    /** @var ProviderInterface[] */
    private array $chain = [];

    /** @var string[] */
    private array $log = [];

    /**
     * @param string   $primary          e.g. "openrouter"
     * @param string[] $fallbacks        e.g. ["gemini","openai"]
     * @param int      $attemptsPerProvider  retries on transient errors (not on 429)
     */
    public function __construct(
        private readonly string $primary,
        private readonly array  $fallbacks,
        private readonly int    $attemptsPerProvider = 2,
    ) {
        $this->chain = $this->buildChain();
    }

    /** Build the chain from config, dropping unknown and unconfigured providers. */
    private function buildChain(): array
    {
        $names = array_unique(array_merge([$this->primary], $this->fallbacks));
        $chain = [];

        foreach ($names as $rawName) {
            $name = strtolower(trim((string) $rawName));
            if ($name === '') {
                continue;
            }

            $provider = $this->make($name);
            if ($provider === null) {
                $this->log[] = "skip {$name}: unknown provider";
                continue;
            }

            if (!$provider->isConfigured()) {
                $this->log[] = "skip {$name}: no API key";
                continue;
            }

            $chain[] = $provider;
        }

        return $chain;
    }

    private function make(string $name): ?ProviderInterface
    {
        return match ($name) {
            'gemini'     => new GeminiProvider(),
            'openai'     => new OpenAIProvider(),
            'openrouter' => new OpenRouterProvider(),
            default      => null,
        };
    }

    /**
     * Run a chat completion through the chain.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array{model?: string, temperature?: float, max_tokens?: int} $options
     *
     * @throws AllProvidersExhaustedException when every provider fails
     */
    public function chat(array $messages, array $options = []): ProviderResponse
    {
        if ($this->chain === []) {
            throw new AllProvidersExhaustedException(
                'No AI provider is configured. Set at least one of: ' .
                'OPENROUTER_API_KEY, OPENAI_API_KEY, GEMINI_API_KEY, CLAUDE_API_KEY.',
                $this->log
            );
        }

        $lastError = null;

        foreach ($this->chain as $provider) {
            for ($attempt = 1; $attempt <= $this->attemptsPerProvider; $attempt++) {
                try {
                    $response = $provider->chat($messages, $options);
                    $this->log[] = "ok {$provider->name()}" . ($attempt > 1 ? " (attempt {$attempt})" : '');
                    return $response;
                } catch (RateLimitException $e) {
                    // 429 — do not retry this provider now. Move on.
                    $lastError = $e;
                    $this->log[] = "{$provider->name()} rate limited — moving to next provider";
                    break;
                } catch (ProviderException $e) {
                    $lastError = $e;
                    $this->log[] = "{$provider->name()} attempt {$attempt} failed: {$e->getMessage()}";

                    if ($attempt < $this->attemptsPerProvider) {
                        // 250 ms, then 500 ms
                        usleep(250_000 * (2 ** ($attempt - 1)));
                    }
                }
            }
        }

        throw new AllProvidersExhaustedException(
            'All AI providers failed. Last error: ' . ($lastError?->getMessage() ?? 'unknown'),
            $this->log
        );
    }

    /** @return string[] provider names actually in the chain, in order */
    public function chain(): array
    {
        return array_map(static fn(ProviderInterface $p) => $p->name(), $this->chain);
    }

    /** @return string[] human-readable trace of how the chain was built and used */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * Factory from environment.
     *
     * FALLBACK_LLM_PROVIDER is a comma-separated list, e.g. "gemini,openai".
     */
    public static function fromEnv(): self
    {
        $primary = trim((string) ($_ENV['LLM_PROVIDER'] ?? 'openrouter'));
        $raw     = trim((string) ($_ENV['FALLBACK_LLM_PROVIDER'] ?? 'gemini,openai'));

        $fallbacks = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return new self($primary, $fallbacks);
    }
}
