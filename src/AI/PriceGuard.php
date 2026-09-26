<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Estimates token usage and cost before a request is sent, blocking prompts
 * that would exceed the configured budget.
 */
final class PriceGuard
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function inspect(string $prompt, string $provider, ?int $maxOutputTokens = null): PriceGuardResult
    {
        $input = $this->estimateTokens($prompt);
        $output = $maxOutputTokens
            ?? (int) ($this->config['max_output_tokens'] ?? 2000);

        $cost = $this->estimateCost($provider, $input, $output);

        $maxInput = (int) ($this->config['max_input_tokens'] ?? 8000);
        $maxOutput = (int) ($this->config['max_output_tokens'] ?? 2000);
        $maxCost = (float) ($this->config['max_cost_usd'] ?? 0.50);

        if ($input > $maxInput) {
            return PriceGuardResult::block($input, $output, $cost, sprintf('Input token estimate %d exceeds limit %d.', $input, $maxInput));
        }

        if ($output > $maxOutput) {
            return PriceGuardResult::block($input, $output, $cost, sprintf('Output token estimate %d exceeds limit %d.', $output, $maxOutput));
        }

        if ($cost > $maxCost) {
            return PriceGuardResult::block($input, $output, $cost, sprintf('Estimated cost $%.4f exceeds limit $%.4f.', $cost, $maxCost));
        }

        return new PriceGuardResult(true, $input, $output, $cost);
    }

    public function estimateTokens(string $text): int
    {
        $characters = mb_strlen($text, 'UTF-8');

        // Rough heuristic: ~4 characters per token.
        return (int) max(1, ceil($characters / 4));
    }

    public function estimateCost(string $provider, int $inputTokens, int $outputTokens): float
    {
        $prices = $this->config['prices'][$provider] ?? ['input' => 0.0, 'output' => 0.0];

        $inputCost = ($inputTokens / 1000) * (float) $prices['input'];
        $outputCost = ($outputTokens / 1000) * (float) $prices['output'];

        return round($inputCost + $outputCost, 6);
    }
}
