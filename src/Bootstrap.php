<?php

declare(strict_types=1);

namespace Orin;

use Orin\AI\BanglishPrompt;
use Orin\AI\PriceGuard;
use Orin\AI\Providers\GeminiProvider;
use Orin\AI\Providers\OpenAIProvider;
use Orin\AI\Providers\OpenRouterProvider;
use Orin\AI\ResilientAIEngine;
use Orin\Support\Config;
use Orin\Support\Http\HttpClient;
use Orin\Support\Logger;

/**
 * Wires the whole engine together from configuration.
 */
final class Bootstrap
{
    private function __construct(private ResilientAIEngine $engine)
    {
    }

    public static function create(string $basePath): self
    {
        $config = Config::fromEnv($basePath);

        $logger = new Logger((string) $config->get('log_path', $basePath . '/orin.log'));
        $http = new HttpClient($config->getInt('timeout', 30));
        $banglish = new BanglishPrompt();
        $priceGuard = new PriceGuard($config->priceGuardConfig());

        $engine = new ResilientAIEngine(
            $config->getList('providers'),
            $banglish,
            $priceGuard,
            $logger,
            $config->getInt('max_retries', 2),
        );

        $engine->register(new OpenAIProvider($config->providerConfig('openai'), $http));
        $engine->register(new GeminiProvider($config->providerConfig('gemini'), $http));
        $engine->register(new OpenRouterProvider($config->providerConfig('openrouter'), $http));

        return new self($engine);
    }

    public function engine(): ResilientAIEngine
    {
        return $this->engine;
    }
}
