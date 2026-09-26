<?php

declare(strict_types=1);

namespace Orin\Tests\Core;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesStaticRoute(): void
    {
        $router = new Router();
        $router->get('/hello', [FakeController::class, 'hello']);

        $response = $router->dispatch(new Request('GET', '/hello'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('hello', $response->body);
    }

    public function testReturnsNotFoundForUnknownPath(): void
    {
        $router = new Router();
        $router->get('/known', [FakeController::class, 'hello']);

        $response = $router->dispatch(new Request('GET', '/missing'));

        self::assertSame(404, $response->status);
    }

    public function testCapturesPathParameters(): void
    {
        $router = new Router();
        $router->get('/users/{id}', [FakeController::class, 'show']);

        $response = $router->dispatch(new Request('GET', '/users/42'));

        self::assertSame(200, $response->status);
        self::assertSame('42', $response->body);
    }
}

final class FakeController
{
    public function hello(Request $request): Response
    {
        return Response::html('hello');
    }

    public function show(Request $request): Response
    {
        return Response::html((string) ($request->attributes['route_params']['id'] ?? ''));
    }
}
