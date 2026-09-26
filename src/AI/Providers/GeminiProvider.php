<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

/**
 * Google Gemini — generativelanguage.googleapis.com
 *
 * NOTE: the free tier is 20 requests/day for flash models. That is not
 * production capacity. Either enable billing on the key, or make sure
 * another provider sits ahead of Gemini in the failover chain.
 */
final class GeminiProvider extends AbstractProvider
{
    public function name(): string { return 'gemini'; }

    public function isConfigured(): bool
    {
        return $this->env('GEMINI_API_KEY') !== '';
    }

    protected function defaultModel(): string
    {
        return $this->env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    protected function endpoint(): string
    {
        $model = $this->env('GEMINI_MODEL', 'gemini-2.5-flash');
        $key   = $this->env('GEMINI_API_KEY');
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
    }

    protected function headers(): array
    {
        return []; // auth travels in the query string
    }

    protected function buildBody(array $messages, array $options): array
    {
        $system = '';
        $contents = [];

        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system .= ($system === '' ? '' : "\n\n") . $m['content'];
                continue;
            }
            $contents[] = [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? 0.4,
                'maxOutputTokens' => $options['max_tokens'] ?? 1024,
            ],
        ];

        if ($system !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        return $body;
    }

    protected function extractText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $out = '';
        foreach ($parts as $part) {
            $out .= $part['text'] ?? '';
        }
        return trim($out);
    }

    protected function extractUsage(array $response): array
    {
        $usage = $response['usageMetadata'] ?? [];
        return [(int) ($usage['promptTokenCount'] ?? 0), (int) ($usage['candidatesTokenCount'] ?? 0)];
    }
}
