<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * File-based sliding-window rate limiter.
 */
final class RateLimiter
{
    public function __construct(private string $storagePath)
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $bucket = $this->load($key, $decaySeconds);

        return count($bucket) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $bucket = $this->load($key, $decaySeconds);
        $bucket[] = time();
        $this->save($key, $bucket);

        return count($bucket);
    }

    public function clear(string $key): void
    {
        @unlink($this->file($key));
    }

    /** @return array<int, int> */
    private function load(string $key, int $decaySeconds): array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file) ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $cutoff = time() - $decaySeconds;

        return array_values(array_filter($data, static fn ($t): bool => is_int($t) && $t >= $cutoff));
    }

    /** @param array<int, int> $bucket */
    private function save(string $key, array $bucket): void
    {
        @file_put_contents($this->file($key), json_encode(array_values($bucket)), LOCK_EX);
    }

    private function file(string $key): string
    {
        return $this->storagePath . '/' . sha1($key) . '.ratelimit';
    }
}
