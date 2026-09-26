<?php

declare(strict_types=1);

namespace Orin\AI;

use Orin\AI\Exceptions\AllProvidersFailedException;
use Orin\Support\Logger;

/**
 * Orchestrates providers with retries, failover and price guarding.
 */
final class ResilientAIEngine
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /** @param array<int, string> $priority */
    public function __construct(
        private array $priority,
        private BanglishPrompt $banglish,
        private PriceGuard $priceGuard,
        private Logger $logger,
        private int $maxRetries = 2,
    ) {
    }

    public function register(ProviderInterface $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function complete(string $prompt, array $options = []): ProviderResponse
    {
        $prompt = $this->banglish->normalise($prompt);
        $errors = [];

        foreach ($this->priority as $name) {
            $provider = $this->providers[$name] ?? null;
            if ($provider === null || !$provider->isConfigured()) {
                continue;
            }

            $guard = $this->priceGuard->inspect($prompt, $name);
            if (!$guard->allowed) {
                $this->logger->warning('Price guard blocked request', ['provider' => $name, 'reason' => $guard->reason]);
                throw new Exceptions\PriceGuardException((string) $guard->reason);
            }

            $attempt = 0;
            while ($attempt <= $this->maxRetries) {
                $attempt++;
                try {
                    return $provider->complete($prompt, $options);
                } catch (Exceptions\TransportException | Exceptions\ProviderException $e) {
                    $errors[$name] = $e->getMessage();
                    $this->logger->warning('Provider attempt failed', [
                        'provider' => $name,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        throw new AllProvidersFailedException(
            'All providers failed: ' . json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
