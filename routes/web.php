<?php

declare(strict_types=1);

use Orin\Http\Controllers\Public\HomeController;

/** @var Orin\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);

// Placeholders wired in later phases:
// $router->get('/pricing',  [PricingController::class, 'index']);
// $router->get('/signup',   [SignupController::class, 'show']);
// $router->post('/signup',  [SignupController::class, 'store']);
// $router->get('/login',    [AuthController::class, 'show']);
// $router->post('/login',   [AuthController::class, 'login']);
// $router->group('/merchant', ['auth','role:merchant'], function ($r) { ... });
// $router->group('/admin',    ['auth','role:admin'],    function ($r) { ... });
