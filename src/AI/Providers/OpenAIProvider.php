<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

use Orin\AI\ProviderResponse;

/**
 * OpenAI Chat Completions provider.
 */
final class OpenAIProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function complete(string $prompt, array $options = []): ProviderResponse
    {
        $started = microtime(true);

        $response = $this->send(
            $this->baseUrl() . '/chat/completions',
            [
                'model' => $this->model(),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Content-Type' => 'application/json',
            ]
        );

        $this->assertSuccessful($response);

        $json = $response->json ?? [];
        $usage = $json['usage'] ?? [];

        return new ProviderResponse(
            content: $this->extractOpenAiContent($json),
            provider: $this->name(),
            model: $this->model(),
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usage['completion_tokens'] ?? 0),
            latencyMs: $this->elapsed($started),
            raw: $json,
        );
    }
}
