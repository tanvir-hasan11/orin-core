<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\AI\PriceGuard;
use PHPUnit\Framework\TestCase;

final class PriceGuardTest extends TestCase
{
    public function testBlocksWhenInputTooLarge(): void
    {
        $guard = new PriceGuard([
            'max_input_tokens' => 10,
            'max_output_tokens' => 10,
            'max_cost_usd' => 100.0,
            'prices' => ['openai' => ['input' => 1.0, 'output' => 1.0]],
        ]);

        $result = $guard->inspect(str_repeat('a', 400), 'openai');

        self::assertFalse($result->allowed);
    }

    public function testAllowsSmallPrompt(): void
    {
        $guard = new PriceGuard([
            'max_input_tokens' => 1000,
            'max_output_tokens' => 1000,
            'max_cost_usd' => 100.0,
            'prices' => ['openai' => ['input' => 0.1, 'output' => 0.1]],
        ]);

        $result = $guard->inspect('hello world', 'openai');

        self::assertTrue($result->allowed);
    }
}
