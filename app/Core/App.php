<?php

declare(strict_types=1);

namespace Orin\Core;

use Orin\Channels\MessengerClient;
use Orin\Channels\WhatsAppClient;
use Orin\Services\AiGateway;
use Orin\Services\ApiKeyService;
use Orin\Services\AuditService;
use Orin\Services\AuthService;
use Orin\Services\ChannelService;
use Orin\Services\ConversationService;
use Orin\Services\InboundMessageProcessor;
use Orin\Services\LeadService;
use Orin\Services\MerchantService;
use Orin\Services\OutboundSender;
use Orin\Services\ReplyDirectiveParser;
use Orin\Services\VerticalPromptBuilder;
use Orin\Services\VerticalRegistry;
use Orin\Support\Container;
use Orin\Support\Http\HttpClient;
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
            'verticals' => require $basePath . '/config/verticals.php',
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

        $logger = new Logger($this->basePath . '/storage/logs/app.log');
        $this->container->set('logger', $logger);

        $db = new Database($this->config['database']);
        $this->container->set('db', $db);

        $this->container->set('view', new View($this->basePath . '/resources/views'));

        $session = new Session((string) $app['session_name'], (int) $app['session_lifetime']);
        $this->container->set('session', $session);
        $this->container->set('csrf', new Csrf($session));
        $this->container->set('rate_limiter', new RateLimiter($this->basePath . '/storage/cache'));
        $this->container->set('router', $this->router);

        $crypto = new Crypto((string) ($app['key'] ?? ''));
        $this->container->set('crypto', $crypto);

        $audit = new AuditService($db);
        $this->container->set('audit', $audit);
        $this->container->set('auth', new AuthService($db, $session, $audit));
        $this->container->set('merchant_service', new MerchantService($db));
        $this->container->set('api_key_service', new ApiKeyService($db, $audit));

        $verticals = new VerticalRegistry(is_array($this->config['verticals'] ?? null) ? $this->config['verticals'] : []);
        $this->container->set('vertical_registry', $verticals);

        $conversations = new ConversationService($db);
        $this->container->set('conversation_service', $conversations);

        $leads = new LeadService($db, $verticals, $audit);
        $this->container->set('lead_service', $leads);

        $channels = new ChannelService($db, $crypto, $audit);
        $this->container->set('channel_service', $channels);

        $http = new HttpClient((int) ($this->config['engine']['timeout'] ?? 30));
        $this->container->set('whatsapp_client', new WhatsAppClient($http, $crypto));
        $this->container->set('messenger_client', new MessengerClient($http, $crypto));

        $this->container->set('outbound_sender', new OutboundSender(
            $this->container->get('whatsapp_client'),
            $this->container->get('messenger_client'),
            $channels,
            $logger,
        ));

        $this->container->set('ai_gateway', new AiGateway(
            $db,
            $logger,
            $crypto,
            is_array($this->config['engine'] ?? null) ? $this->config['engine'] : [],
        ));

        $this->container->set('vertical_prompt_builder', new VerticalPromptBuilder($verticals));

        $this->container->set('inbound_processor', new InboundMessageProcessor(
            $db,
            $channels,
            $conversations,
            $leads,
            $this->container->get('vertical_prompt_builder'),
            $this->container->get('ai_gateway'),
            new ReplyDirectiveParser(),
            $this->container->get('outbound_sender'),
            $logger,
            (int) ($app['reply_history_limit'] ?? 12),
        ));
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
