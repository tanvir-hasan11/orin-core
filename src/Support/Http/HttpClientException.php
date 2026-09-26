<?php

declare(strict_types=1);

namespace Orin\Support\Http;

use RuntimeException;

/**
 * Raised when the transport layer itself fails (DNS, timeout, curl error...).
 */
final class HttpClientException extends RuntimeException
{
}
