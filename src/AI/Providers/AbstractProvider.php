<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

use Orin\AI\Exceptions\ProviderException;
use Orin\AI\Exceptions\TransportException;
use Orin\AI\ProviderInterface;
use Orin\AI\ProviderResponse;
use Orin\Support\Http\HttpClient;
use Orin\Support\Http\HttpClientException;
use Orin\Support\Http\HttpResponse;

/**
 * Shared HTTP and parsing helpers for concrete providers.
 */
abstract class AbstractProvider implements ProviderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected array $config,
        protected HttpClient $http,
    ) {
    }

    public function isConfigured(): bool
    {
        return (string) ($this->config['api_key'] ?? '') !== '';
    }

    protected function model(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    protected function apiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    protected function send(string $url, array $payload, array $headers): HttpResponse
    {
        try {
            return $this->http->postJson($url, $payload, $headers);
        } catch (HttpClientException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    protected function assertSuccessful(HttpResponse $response): void
    {
        if (!$response->isSuccessful()) {
            $message = is_array($response->json)
                ? (string) ($response->json['error']['message'] ?? $response->json['error'] ?? $response->body)
                : $response->body;

            throw new ProviderException(sprintf('%s returned HTTP %d: %s', $this->name(), $response->status, $message));
        }
    }

    /**
     * Best-effort content extraction shared by OpenAI-compatible APIs.
     *
     * @param array<string, mixed>|null $json
     */
    protected function extractOpenAiContent(?array $json): string
    {
        $choices = $json['choices'] ?? null;
        if (is_array($choices) && isset($choices[0]['message']['content'])) {
            return (string) $choices[0]['message']['content'];
        }

        return '';
    }

    protected function elapsed(int $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
