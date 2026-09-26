<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Detects Banglish (Bengali romanised in Latin script) and enriches the prompt
 * with a system instruction so responses stay useful for Banglish users.
 */
final class BanglishPrompt
{
    private const SYSTEM_INSTRUCTION = 'The user is writing in Banglish (Bengali written using the Latin alphabet). Understand their intent and reply in the same Banglish style unless they ask for another language.';

    /** @var array<int, string> */
    private const MARKERS = [
        'ki', 'kivabe', 'keno', 'kothay', 'koro', 'korbo', 'korle', 'kore',
        'ami', 'tumi', 'apni', 'acho', 'achen', 'hobe', 'hoy', 'chai',
        'bhai', 'valo', 'bhalo', 'khub', 'onek', 'ekta', 'kotha', 'bolo',
    ];

    public function detect(string $prompt): bool
    {
        $words = preg_split('/\s+/', mb_strtolower($prompt, 'UTF-8')) ?: [];
        $hits = 0;

        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z]/', '', $word) ?? '';
            if ($clean !== '' && in_array($clean, self::MARKERS, true)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    public function normalise(string $prompt): string
    {
        if (!$this->detect($prompt)) {
            return $prompt;
        }

        return self::SYSTEM_INSTRUCTION . "\n\n" . $prompt;
    }
}
