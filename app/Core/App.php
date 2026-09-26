<?php

declare(strict_types=1);

namespace Orin\Core;

use Orin\Support\Container;
use Orin\Support\Logger;
use Throwable;

final class App
{
    private Container $container;

    /** @param array<string, mixed> $config */
    private function __construct(
        private string $basePath,
        private array $config,
        private Router $router,
    ) {
        $this->container = new Container();
    }

    public static function boot(string $basePath): self
    {
        $basePath = rtrim($basePath, '/');

        $config = [
            'app' => require $basePath . '/config/app.php',
            'database' => require $basePath . '/config/database.php',
            'engine' => require $basePath . '/config/config.php',
        ];

        date_default_timezone_set((string) $config['app']['timezone']);

        foreach (['/storage/logs', '/storage/cache', '/storage/sessions'] as $dir) {
            if (!is_dir($basePath . $dir)) {
                @mkdir($basePath . $dir, 0775, true);
            }
        }

        $router = new Router();

        $register = static function (Router $router, string $file): void {
            require $file;
        };
        $register($router, $basePath . '/routes/web.php');
        $register($router, $basePath . '/routes/api.php');

        $app = new self($basePath, $config, $router);
        $app->registerServices();

        $GLOBALS['__orin_container'] = $app->container;

        return $app;
    }

    private function registerServices(): void
    {
        $app = $this->config['app'];

        $this->container->set('base_path', $this->basePath);
        $this->container->set('config', $this->config);
        $this->container->set('logger', new Logger($this->basePath . '/storage/logs/app.log'));
        $this->container->set('db', new Database($this->config['database']));
        $this->container->set('view', new View($this->basePath . '/resources/views'));
        $this->container->set('session', new Session((string) $app['session_name'], (int) $app['session_lifetime']));
        $this->container->set('csrf', new Csrf($this->container->get('session')));
        $this->container->set('rate_limiter', new RateLimiter($this->basePath . '/storage/cache'));
        $this->container->set('router', $this->router);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function run(): void
    {
        try {
            $this->container->get('session')->start();

            $request = Request::fromGlobals();

            $middleware = [
                new Middleware\ThrottleMiddleware($this->container),
                new Middleware\CsrfMiddleware($this->container),
                new Middleware\AuthMiddleware($this->container),
                new Middleware\RoleMiddleware($this->container),
            ];

            $response = $this->router->dispatch($request, $middleware);
        } catch (Throwable $e) {
            $this->container->get('logger')->error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $response = $this->config['app']['debug']
                ? Response::html('<pre>' . e($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>', 500)
                : Response::html('<h1>500</h1><p>Something went wrong.</p>', 500);
        }

        $response->send();
    }
}
