<?php

declare(strict_types=1);

namespace Orin\Core;

use RuntimeException;

/**
 * Small regex router supporting path parameters and per-route middleware.
 */
final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    /** @var array<int, string> */
    private array $groupMiddleware = [];

    /** @param array<int, mixed> $handler */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param array<int, mixed> $handler */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param array<int, mixed> $handler */
    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    /** @param array<int, mixed> $handler */
    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /** @param array<int, string> $middleware */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($previous, $middleware);

        $previousPrefix = $this->currentPrefix ?? '';
        $this->currentPrefix = $previousPrefix . $prefix;

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->groupMiddleware = $previous;
    }

    private ?string $currentPrefix = '';

    /** @param array<int, mixed> $handler */
    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $full = rtrim(($this->currentPrefix ?? '') . $path, '/');
        if ($full === '') {
            $full = '/';
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $full,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'regex' => $this->compile($full),
        ];
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    /** @param array<int, object> $middleware */
    public function dispatch(Request $request, array $middleware = []): Response
    {
        $path = $request->path;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = array_filter($matches, static fn ($k) => !is_int($k), ARRAY_FILTER_USE_KEY);

            return $this->runChain($route, $request, $params, $middleware);
        }

        return Response::notFound();
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, string> $params
     * @param array<int, object> $middleware
     */
    private function runChain(array $route, Request $request, array $params, array $middleware): Response
    {
        $request->attributes['route_params'] = $params;

        $required = $route['middleware'];

        $pipeline = [];
        foreach ($middleware as $mw) {
            $pipeline[$this->middlewareName($mw)] = $mw;
        }

        $runner = function (int $index) use (&$runner, $required, $pipeline, $route, $request): Response {
            if ($index >= count($required)) {
                return $this->invoke($route, $request);
            }

            $name = $required[$index];
            $key = explode(':', $name, 2)[0];

            if (!isset($pipeline[$key])) {
                throw new RuntimeException(sprintf('Middleware "%s" is not registered.', $key));
            }

            return $pipeline[$key]->handle($request, fn (Request $req): Response => $runner($index + 1));
        };

        return $runner(0);
    }

    private function middlewareName(object $middleware): string
    {
        if (method_exists($middleware, 'name')) {
            return (string) $middleware->name();
        }

        $class = $middleware::class;
        $short = substr($class, strrpos($class, '\\') + 1);

        return strtolower(str_replace('Middleware', '', $short));
    }

    /** @param array<string, mixed> $route */
    private function invoke(array $route, Request $request): Response
    {
        [$class, $method] = $route['handler'];

        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('Controller %s not found.', $class));
        }

        $controller = new $class();

        $result = $controller->{$method}($request);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
    }
}
