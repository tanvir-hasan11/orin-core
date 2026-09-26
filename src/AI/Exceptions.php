<?php

declare(strict_types=1);

namespace Orin\AI;

/** Base for every AI-layer failure. */
class ProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

/** HTTP 429 or an explicit quota-exceeded response. */
final class RateLimitException extends ProviderException {}

/** Every provider in the chain failed. */
final class AllProvidersExhaustedException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $attemptLog = [])
    {
        parent::__construct($message);
    }
}
