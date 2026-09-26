<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\AI\BanglishPrompt;
use Orin\AI\PriceGuard;
use Orin\AI\ProviderInterface;
use Orin\AI\ProviderResponse;
use Orin\AI\Providers\GeminiProvider;
use Orin\AI\Providers\OpenAIProvider;
use Orin\AI\Providers\OpenRouterProvider;
use Orin\AI\ResilientAIEngine;
use Orin\Core\Crypto;
use Orin\Core\Database;
use Orin\Support\Http\HttpClient;
use Orin\Support\Logger;

/**
 * The only place in the application that talks to an AI provider.
 *
 * Which providers exist, which are enabled and which API keys they use is a
 * platform decision owned by the super admin (platform_providers table).
 * Merchants never choose a provider - they just get replies.
 *
 * When platform_providers is empty the gateway falls back to the keys in the
 * environment so a fresh install still works.
 */
final class AiGateway
{
    /** @param array<string, mixed> $engineConfig */
    public function __construct(
        private Database $db,
        private Logger $logger,
        private Crypto $crypto,
        private array $engineConfig = [],
    ) {
    }

    /** @param array<string, mixed> $options */
    public function complete(string $prompt, array $options = []): ProviderResponse
    {
        return $this->engine()->complete($prompt, $options);
    }

    /** @return array<int, string> provider names in the order they will be tried */
    public function chain(): array
    {
        return $this->engine()->chain();
    }

    private function engine(): ResilientAIEngine
    {
        $timeout = (int) ($this->engineConfig['timeout'] ?? 30);
        $http = new HttpClient($timeout);

        $rows = $this->db->select(
            'SELECT provider, api_key, default_model FROM platform_providers WHERE enabled = 1 ORDER BY priority ASC, id ASC'
        );

        $sources = [];
        if ($rows !== []) {
            foreach ($rows as $row) {
                $name = (string) $row['provider'];
                $sources[$name] = [
                    'api_key' => $this->crypto->decrypt((string) ($row['api_key'] ?? '')),
                    'model' => (string) ($row['default_model'] ?? ''),
                ];
            }
        } else {
            foreach (($this->engineConfig['providers'] ?? []) as $name) {
                $cfg = $this->engineConfig['providers_config'][$name] ?? [];
                $sources[(string) $name] = [
                    'api_key' => (string) ($cfg['api_key'] ?? ''),
                    'model' => (string) ($cfg['model'] ?? ''),
                    'base_url' => (string) ($cfg['base_url'] ?? ''),
                ];
            }
        }

        $priority = [];
        foreach ($sources as $name => $cfg) {
            if (trim((string) ($cfg['api_key'] ?? '')) === '') {
                continue;
            }
            $priority[] = $name;
        }

        $engine = new ResilientAIEngine(
            $priority,
            new BanglishPrompt(),
            new PriceGuard(is_array($this->engineConfig['price_guard'] ?? null) ? $this->engineConfig['price_guard'] : []),
            $this->logger,
            (int) ($this->engineConfig['max_retries'] ?? 2),
        );

        foreach ($priority as $name) {
            $provider = $this->make($name, $sources[$name], $http);
            if ($provider instanceof ProviderInterface) {
                $engine->register($provider);
            }
        }

        return $engine;
    }

    /** @param array<string, mixed> $cfg */
    private function make(string $name, array $cfg, HttpClient $http): ?ProviderInterface
    {
        $config = [
            'api_key' => (string) ($cfg['api_key'] ?? ''),
            'model' => (string) ($cfg['model'] ?? ''),
            'base_url' => (string) ($cfg['base_url'] ?? ''),
        ];

        return match (strtolower($name)) {
            'openai' => new OpenAIProvider($this->withDefault($config, 'https://api.openai.com/v1', 'gpt-4o-mini'), $http),
            'gemini' => new GeminiProvider($this->withDefault($config, 'https://generativelanguage.googleapis.com/v1beta', 'gemini-1.5-flash'), $http),
            'openrouter' => new OpenRouterProvider($this->withDefault($config, 'https://openrouter.ai/api/v1', 'openai/gpt-4o-mini'), $http),
            default => null,
        };
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    private function withDefault(array $config, string $baseUrl, string $model): array
    {
        if (($config['base_url'] ?? '') === '') {
            $config['base_url'] = $baseUrl;
        }
        if (($config['model'] ?? '') === '') {
            $config['model'] = $model;
        }

        return $config;
    }
}
