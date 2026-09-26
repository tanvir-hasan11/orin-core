<?php

declare(strict_types=1);

use Orin\Http\Controllers\Admin\DashboardController as AdminDashboard;
use Orin\Http\Controllers\Merchant\ApiKeyController;
use Orin\Http\Controllers\Merchant\BillingController;
use Orin\Http\Controllers\Merchant\DashboardController as MerchantDashboard;
use Orin\Http\Controllers\Merchant\SettingsController;
use Orin\Http\Controllers\Merchant\UsageController;
use Orin\Http\Controllers\Public\AuthController;
use Orin\Http\Controllers\Public\HomeController;
use Orin\Http\Controllers\Public\PasswordResetController;
use Orin\Http\Controllers\Public\SignupController;

/** @var Orin\Core\Router $router */

// ------------------------------------------------------------------ public
$router->get('/', [HomeController::class, 'index']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/signup', [SignupController::class, 'show']);
$router->post('/signup', [SignupController::class, 'store']);

$router->get('/forgot-password', [PasswordResetController::class, 'showForgot']);
$router->post('/forgot-password', [PasswordResetController::class, 'send']);
$router->get('/reset-password', [PasswordResetController::class, 'showReset']);
$router->post('/reset-password', [PasswordResetController::class, 'reset']);

// ------------------------------------------------------------ merchant area
// Merchants do NOT choose AI providers. Provider selection, platform keys and
// pricing are super-admin concerns. Merchants only consume the API.
$router->group('/merchant', ['auth', 'role:merchant'], function ($r) {
    $r->get('/dashboard', [MerchantDashboard::class, 'index']);

    $r->get('/api-keys', [ApiKeyController::class, 'index']);
    $r->post('/api-keys', [ApiKeyController::class, 'store']);
    $r->post('/api-keys/{id}/revoke', [ApiKeyController::class, 'revoke']);

    $r->get('/usage', [UsageController::class, 'index']);

    $r->get('/billing', [BillingController::class, 'index']);

    $r->get('/settings', [SettingsController::class, 'index']);
    $r->post('/settings', [SettingsController::class, 'update']);
    $r->post('/settings/password', [SettingsController::class, 'updatePassword']);
});

// --------------------------------------------------------------- admin area
$router->group('/admin', ['auth', 'role:admin'], function ($r) {
    $r->get('/dashboard', [AdminDashboard::class, 'index']);
});
