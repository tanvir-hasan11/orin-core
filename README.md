# Orin Core

A resilient, multi-provider AI engine for PHP 8.1+.

Orin Core gives you one clean interface over OpenAI, Google Gemini and
OpenRouter, with automatic failover, retry handling, cost guarding and
Banglish (Bengali written in Latin script) prompt handling built in.

## Features

- **One interface, many providers** - `Orin\AI\ProviderInterface` abstracts
  OpenAI, Gemini and OpenRouter behind a single `complete()` call.
- **Resilient engine** - `ResilientAIEngine` tries each provider in order,
  retries on transient failures, and falls back to the next provider when one
  is down or misconfigured.
- **Price guard** - `PriceGuard` estimates token usage and cost before a
  request is sent, blocking prompts that exceed your configured budget.
- **Banglish support** - `BanglishPrompt` normalises romanised Bengali input
  and injects a system instruction so responses stay useful.
- **Zero hard dependencies** - only `ext-curl` and `ext-json` are required.

## Installation

```bash
composer require tanvir-hasan11/orin-core
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Orin\Bootstrap;

$app = Bootstrap::create(__DIR__);
$engine = $app->engine();

$response = $engine->complete('Explain dependency injection in one paragraph.');

echo $response->content;
```

Copy `.env.example` to `.env` and fill in at least one provider API key.

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `ORIN_AI_PROVIDERS` | `openai,gemini,openrouter` | Provider priority order |
| `ORIN_MAX_RETRIES` | `2` | Retries per provider before failover |
| `ORIN_TIMEOUT` | `30` | HTTP timeout in seconds |
| `ORIN_PRICE_MAX_INPUT_TOKENS` | `8000` | Hard input token cap |
| `ORIN_PRICE_MAX_OUTPUT_TOKENS` | `2000` | Hard output token cap |
| `ORIN_PRICE_MAX_COST_USD` | `0.50` | Max estimated cost per request |

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for the full breakdown of the engine,
providers and support layers.

## License

MIT
