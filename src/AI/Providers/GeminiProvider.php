<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

use Orin\AI\ProviderResponse;

/**
 * Google Gemini provider (generateContent API).
 */
final class GeminiProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function complete(string $prompt, array $options = []): ProviderResponse
    {
        $started = microtime(true);

        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            $this->baseUrl(),
            $this->model(),
            urlencode($this->apiKey())
        );

        $response = $this->send(
            $url,
            [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ],
            ['Content-Type' => 'application/json']
        );

        $this->assertSuccessful($response);

        $json = $response->json ?? [];
        $candidates = $json['candidates'] ?? [];
        $content = '';

        if (is_array($candidates) && isset($candidates[0]['content']['parts'][0]['text'])) {
            $content = (string) $candidates[0]['content']['parts'][0]['text'];
        }

        $usage = $json['usageMetadata'] ?? [];

        return new ProviderResponse(
            content: $content,
            provider: $this->name(),
            model: $this->model(),
            promptTokens: (int) ($usage['promptTokenCount'] ?? 0),
            completionTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            latencyMs: $this->elapsed($started),
            raw: $json,
        );
    }
}
