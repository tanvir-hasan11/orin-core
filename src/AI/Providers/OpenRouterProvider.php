<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

/**
 * OpenRouter — openrouter.ai
 *
 * OpenAI-compatible API, but with a much more generous free tier and a
 * wide model catalogue. Recommended as the primary provider when the
 * merchant is on a tight AI budget.
 */
final class OpenRouterProvider extends AbstractProvider
{
    public function name(): string { return 'openrouter'; }

    public function isConfigured(): bool
    {
        return $this->env('OPENROUTER_API_KEY') !== '';
    }

    protected function defaultModel(): string
    {
        return $this->env('OPENROUTER_MODEL', 'google/gemini-2.5-flash');
    }

    protected function endpoint(): string
    {
        return 'https://openrouter.ai/api/v1/chat/completions';
    }

    protected function headers(): array
    {
        $headers = ['Authorization: Bearer ' . $this->env('OPENROUTER_API_KEY')];

        $referer = $this->env('APP_URL');
        if ($referer !== '') {
            $headers[] = 'HTTP-Referer: ' . $referer;
        }
        $headers[] = 'X-Title: ORIN Core';

        return $headers;
    }

    protected function buildBody(array $messages, array $options): array
    {
        return [
            'model'       => $options['model'] ?? $this->defaultModel(),
            'messages'    => array_map(
                fn($m) => ['role' => $m['role'], 'content' => $m['content']],
                $messages
            ),
            'temperature' => $options['temperature'] ?? 0.4,
            'max_tokens'  => $options['max_tokens'] ?? 1024,
        ];
    }

    protected function extractText(array $response): string
    {
        return trim((string) ($response['choices'][0]['message']['content'] ?? ''));
    }

    protected function extractUsage(array $response): array
    {
        $usage = $response['usage'] ?? [];
        return [(int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0)];
    }
}
