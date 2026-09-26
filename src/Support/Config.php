<?php

declare(strict_types=1);

namespace Orin\Support;

/**
 * Immutable-ish configuration bag.
 *
 * Values are resolved from environment variables (optionally loaded from a
 * .env file) and then read through typed accessors.
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    public static function fromEnv(string $basePath): self
    {
        $envFile = rtrim($basePath, '/') . '/.env';
        if (is_file($envFile)) {
            self::loadEnvFile($envFile);
        }

        $values = [
            'providers' => self::csvEnv('ORIN_AI_PROVIDERS'),
            'max_retries' => self::intEnv('ORIN_MAX_RETRIES', 2),
            'timeout' => self::intEnv('ORIN_TIMEOUT', 30),
            'log_path' => self::strEnv('ORIN_LOG_PATH', rtrim($basePath, '/') . '/orin.log'),
            'providers_config' => [
                'openai' => [
                    'api_key' => self::strEnv('OPENAI_API_KEY', ''),
                    'model' => self::strEnv('OPENAI_MODEL', 'gpt-4o-mini'),
                    'base_url' => self::strEnv('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                ],
                'gemini' => [
                    'api_key' => self::strEnv('GEMINI_API_KEY', ''),
                    'model' => self::strEnv('GEMINI_MODEL', 'gemini-1.5-flash'),
                    'base_url' => self::strEnv('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
                ],
                'openrouter' => [
                    'api_key' => self::strEnv('OPENROUTER_API_KEY', ''),
                    'model' => self::strEnv('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
                    'base_url' => self::strEnv('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
                ],
            ],
            'price_guard' => [
                'max_input_tokens' => self::intEnv('ORIN_PRICE_MAX_INPUT_TOKENS', 8000),
                'max_output_tokens' => self::intEnv('ORIN_PRICE_MAX_OUTPUT_TOKENS', 2000),
                'max_cost_usd' => self::floatEnv('ORIN_PRICE_MAX_COST_USD', 0.50),
                'prices' => [
                    'openai' => ['input' => 0.15, 'output' => 0.60],
                    'gemini' => ['input' => 0.075, 'output' => 0.30],
                    'openrouter' => ['input' => 0.15, 'output' => 0.60],
                ],
            ],
        ];

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return array<int, string> */
    public function getList(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? array_values($value) : [];
    }

    /** @return array<string, mixed> */
    public function providerConfig(string $provider): array
    {
        $all = $this->get('providers_config', []);

        return is_array($all) && isset($all[$provider]) && is_array($all[$provider])
            ? $all[$provider]
            : [];
    }

    /** @return array<string, mixed> */
    public function priceGuardConfig(): array
    {
        $value = $this->get('price_guard', []);

        return is_array($value) ? $value : [];
    }

    /** @return array<int, string> */
    private static function csvEnv(string $key): array
    {
        $raw = self::strEnv($key, '');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));
    }

    private static function strEnv(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }

    private static function intEnv(string $key, int $default): int
    {
        $value = getenv($key);

        return $value === false || !is_numeric($value) ? $default : (int) $value;
    }

    private static function floatEnv(string $key, float $default): float
    {
        $value = getenv($key);

        return $value === false || !is_numeric($value) ? $default : (float) $value;
    }

    private static function loadEnvFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
