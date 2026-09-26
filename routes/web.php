<?php

declare(strict_types=1);

use Orin\Http\Controllers\Admin\DashboardController as AdminDashboard;
use Orin\Http\Controllers\Merchant\AgentActionController;
use Orin\Http\Controllers\Merchant\AgentController;
use Orin\Http\Controllers\Merchant\ApiKeyController;
use Orin\Http\Controllers\Merchant\BillingController;
use Orin\Http\Controllers\Merchant\ChannelController;
use Orin\Http\Controllers\Merchant\DashboardController as MerchantDashboard;
use Orin\Http\Controllers\Merchant\FollowUpController;
use Orin\Http\Controllers\Merchant\InboxController;
use Orin\Http\Controllers\Merchant\KnowledgeController;
use Orin\Http\Controllers\Merchant\LeadController;
use Orin\Http\Controllers\Merchant\ProfileController;
use Orin\Http\Controllers\Merchant\SettingsController;
use Orin\Http\Controllers\Merchant\UsageController;
use Orin\Http\Controllers\Public\AuthController;
use Orin\Http\Controllers\Public\HomeController;
use Orin\Http\Controllers\Public\PasswordResetController;
use Orin\Http\Controllers\Public\SignupController;
use Orin\Http\Controllers\Webhook\MessengerWebhookController;
use Orin\Http\Controllers\Webhook\WhatsAppWebhookController;

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

// --------------------------------------------------------------- webhooks
$router->get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
$router->post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
$router->get('/webhooks/messenger', [MessengerWebhookController::class, 'verify']);
$router->post('/webhooks/messenger', [MessengerWebhookController::class, 'receive']);

// ------------------------------------------------------------ merchant area
// The merchant owns their own agent: identity, knowledge, skills and autonomy.
// Provider selection and platform keys stay with the super admin.
$router->group('/merchant', ['auth', 'role:merchant'], function ($r) {
    $r->get('/dashboard', [MerchantDashboard::class, 'index']);

    $r->get('/inbox', [InboxController::class, 'index']);
    $r->get('/inbox/{id}', [InboxController::class, 'show']);
    $r->post('/inbox/{id}/reply', [InboxController::class, 'reply']);
    $r->post('/inbox/{id}/approve-draft', [InboxController::class, 'approveDraft']);
    $r->post('/inbox/{id}/handoff', [InboxController::class, 'handoff']);
    $r->post('/inbox/{id}/resume-ai', [InboxController::class, 'resumeAi']);

    $r->get('/leads', [LeadController::class, 'index']);
    $r->post('/leads/{id}/stage', [LeadController::class, 'updateStage']);

    $r->get('/followups', [FollowUpController::class, 'index']);
    $r->post('/followups/{id}/cancel', [FollowUpController::class, 'cancel']);

    $r->get('/actions', [AgentActionController::class, 'index']);
    $r->post('/actions/{id}/status', [AgentActionController::class, 'setStatus']);

    $r->get('/agents', [AgentController::class, 'index']);
    $r->post('/agents', [AgentController::class, 'create']);
    $r->get('/agents/{id}/edit', [AgentController::class, 'edit']);
    $r->post('/agents/{id}', [AgentController::class, 'update']);
    $r->post('/agents/{id}/status', [AgentController::class, 'toggleStatus']);

    $r->get('/knowledge', [KnowledgeController::class, 'index']);
    $r->post('/knowledge', [KnowledgeController::class, 'store']);
    $r->post('/knowledge/{id}', [KnowledgeController::class, 'update']);
    $r->post('/knowledge/{id}/toggle', [KnowledgeController::class, 'toggle']);
    $r->post('/knowledge/{id}/delete', [KnowledgeController::class, 'delete']);

    $r->get('/channels', [ChannelController::class, 'index']);
    $r->post('/channels/whatsapp', [ChannelController::class, 'connectWhatsApp']);
    $r->post('/channels/messenger', [ChannelController::class, 'connectMessenger']);
    $r->post('/channels/{id}/disconnect', [ChannelController::class, 'disconnect']);

    $r->get('/profile', [ProfileController::class, 'index']);
    $r->post('/profile', [ProfileController::class, 'update']);

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
