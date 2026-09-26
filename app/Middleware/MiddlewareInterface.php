<?php

declare(strict_types=1);

namespace Orin\Middleware;

use Orin\Core\Request;
use Orin\Core\Response;

interface MiddlewareInterface
{
    public function name(): string;

    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next): Response;
}
