<?php

declare(strict_types=1);

/**
 * Orin Core configuration.
 *
 * Returned as a plain array so it can be consumed by the Config object or by
 * any other part of the application that prefers raw arrays.
 */

return [
    'providers' => ['openai', 'gemini', 'openrouter'],

    'engine' => [
        'max_retries' => 2,
        'timeout' => 30,
    ],

    'price_guard' => [
        'max_input_tokens' => 8000,
        'max_output_tokens' => 2000,
        'max_cost_usd' => 0.50,
        'prices' => [
            'openai' => ['input' => 0.15, 'output' => 0.60],
            'gemini' => ['input' => 0.075, 'output' => 0.30],
            'openrouter' => ['input' => 0.15, 'output' => 0.60],
        ],
    ],
];
