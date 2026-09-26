<?php

declare(strict_types=1);

namespace Orin\AI;

use RuntimeException;

/**
 * A provider returned an error response.
 */
class ProviderException extends RuntimeException
{
}

/**
 * The transport layer failed while talking to a provider.
 */
class TransportException extends RuntimeException
{
}

/**
 * Every configured provider failed.
 */
class AllProvidersFailedException extends RuntimeException
{
}

/**
 * A prompt was rejected by the price guard.
 */
class PriceGuardException extends RuntimeException
{
}
