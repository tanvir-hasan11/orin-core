<?php

declare(strict_types=1);

namespace Orin\AI\Providers;

/** OpenAI Chat Completions — api.openai.com */
final class OpenAIProvider extends AbstractProvider
{
    public function name(): string { return 'openai'; }

    public function isConfigured(): bool
    {
        return $this->env('OPENAI_API_KEY') !== '';
    }

    protected function defaultModel(): string
    {
        return $this->env('OPENAI_MODEL', 'gpt-4o-mini');
    }

    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function headers(): array
    {
        return ['Authorization: Bearer ' . $this->env('OPENAI_API_KEY')];
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
