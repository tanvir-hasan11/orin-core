<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Public;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Core\View;

final class HomeController
{
    public function index(Request $request): Response
    {
        $view = new View(base_path('resources/views'));

        $html = $view->render('public/home', [
            'title' => 'Orin - Resilient AI infrastructure',
            'features' => [
                'Multi-provider AI engine (OpenAI, Gemini, OpenRouter)',
                'Automatic retry and failover',
                'Cost and token guarding per request',
                'Merchant API keys with usage tracking',
                'Super admin control panel',
            ],
        ]);

        return Response::html($html);
    }
}
