<?php

declare(strict_types=1);

namespace Orin\AI\Exceptions;

/** A prompt was rejected by the price guard before it left the server. */
class PriceGuardException extends \RuntimeException
{
}
