<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

use Orin\AI\ProviderException;
use Orin\AI\ProviderInterface;
use Orin\AI\RateLimitException;

/**
 * Shared plumbing for HTTP-based providers.
 * Subclasses supply the endpoint, headers, and body shape.
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected const TIMEOUT_SECONDS = 45;

    /** Read an env value, treating blank strings as absent. */
    protected function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key) ?: '';
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? $default : $value;
    }

    protected function envInt(string $key, int $default): int
    {
        $raw = $this->env($key);
        return $raw === '' ? $default : (int) $raw;
    }

    /**
     * POST JSON and return the decoded body.
     *
     * @throws RateLimitException
     * @throws ProviderException
     */
    protected function postJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => static::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ProviderException(
                "{$this->name()}: network error — {$err}",
                null,
                ['url' => $url]
            );
        }

        $decoded = json_decode((string) $raw, true);

        if ($status === 429) {
            $retryAfter = $this->extractRetryAfter($decoded);
            throw new RateLimitException(
                "{$this->name()}: rate limited (429)" . ($retryAfter ? " retry_after={$retryAfter}s" : ''),
                429,
                ['body' => $decoded]
            );
        }

        if ($status < 200 || $status >= 300) {
            $msg = $decoded['error']['message']
                ?? $decoded['message']
                ?? substr((string) $raw, 0, 300);
            throw new ProviderException(
                "{$this->name()}: HTTP {$status} — {$msg}",
                $status,
                ['body' => $decoded]
            );
        }

        if (!is_array($decoded)) {
            throw new ProviderException(
                "{$this->name()}: response was not JSON",
                $status,
                ['raw' => substr((string) $raw, 0, 300)]
            );
        }

        return $decoded;
    }

    private function extractRetryAfter(?array $body): ?int
    {
        if (!is_array($body)) {
            return null;
        }
        foreach (($body['error']['details'] ?? []) as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.rpc.RetryInfo') {
                $delay = $detail['retryDelay'] ?? null;
                if (is_string($delay) && preg_match('/(\d+)/', $delay, $m)) {
                    return (int) $m[1];
                }
            }
        }
        return null;
    }

    /** Convert our neutral message list into the shape a provider expects. */
    abstract protected function buildBody(array $messages, array $options): array;

    /** Pull the assistant text out of a provider's success response. */
    abstract protected function extractText(array $response): string;

    /** Pull token usage out of a provider's success response. */
    abstract protected function extractUsage(array $response): array;

    public function chat(array $messages, array $options = []): \Orin\AI\ProviderResponse
    {
        $response = $this->postJson(
            $this->endpoint(),
            $this->headers(),
            $this->buildBody($messages, $options)
        );

        [$tokensIn, $tokensOut] = $this->extractUsage($response);

        return new \Orin\AI\ProviderResponse(
            text:      $this->extractText($response),
            provider:  $this->name(),
            model:     $options['model'] ?? $this->defaultModel(),
            tokensIn:  $tokensIn,
            tokensOut: $tokensOut,
            raw:       $response,
        );
    }

    abstract protected function endpoint(): string;
    abstract protected function headers(): array;
    abstract protected function defaultModel(): string;
}
