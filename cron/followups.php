<?php

declare(strict_types=1);

/**
 * Sends the follow-ups the agents asked for.
 *
 * cPanel cron, every five minutes:
 *   php /home/USER/orin-core/cron/followups.php
 *
 * Or over HTTP with the shared secret:
 *   curl 'https://your-domain/cron/followups.php?token=CRON_SECRET'
 */

define('ORIN_BASE', dirname(__DIR__));

require ORIN_BASE . '/vendor/autoload.php';

use Orin\Core\App;

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = (string) (getenv('CRON_SECRET') ?: '');
    $given = (string) ($_GET['token'] ?? '');

    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
}

$app = App::boot(ORIN_BASE);
$container = $app->container();

$followups = $container->get('followup_service');
$conversations = $container->get('conversation_service');
$agents = $container->get('agent_service');
$sender = $container->get('outbound_sender');
$ai = $container->get('ai_gateway');
$db = $container->get('db');
$logger = $container->get('logger');

$due = $followups->due(25);
$sent = 0;
$failed = 0;

foreach ($due as $row) {
    $merchantId = (int) $row['merchant_id'];
    $contactId = (int) $row['contact_id'];
    $conversationId = (int) ($row['conversation_id'] ?? 0);
    $agentId = isset($row['agent_id']) ? (int) $row['agent_id'] : null;

    $channel = (string) ($row['contact_channel'] ?? 'whatsapp');
    $connection = $conversations->connectionFor($merchantId, $channel);

    if ($connection === null) {
        $followups->markFailed((int) $row['id']);
        $failed++;
        continue;
    }

    $contact = $db->first('SELECT * FROM contacts WHERE id = :id LIMIT 1', ['id' => $contactId]) ?? [];
    $profile = $conversations->profile($merchantId);
    $agent = $agentId !== null ? $agents->find($merchantId, $agentId) : $agents->defaultFor($merchantId);

    if ($agent === null || (string) $agent['autonomy'] === 'off') {
        $followups->markFailed((int) $row['id']);
        $failed++;
        continue;
    }

    $merchant = $db->first('SELECT company_name FROM merchants WHERE id = :id LIMIT 1', ['id' => $merchantId]) ?? [];

    $prompt = implode("\n\n", [
        'You are ' . (string) $agent['name'] . ', the ' . (string) $agent['role_label'] . ' for ' . (string) ($merchant['company_name'] ?? 'the business') . '.',
        'The customer went quiet. This is a scheduled follow-up.',
        'Reason the follow-up was scheduled: ' . (string) $row['reason'],
        'Write ONE short, warm message that reopens the conversation. Do not apologise for bothering them. Do not invent a price, a discount or a delivery time.',
        'Customer name: ' . (string) ($contact['name'] ?? 'unknown'),
        'Customer phone: ' . (string) ($contact['phone'] ?? 'unknown'),
        'Business facts: ' . (string) ($profile['description'] ?? '') . ' Delivery: ' . (string) ($profile['delivery_info'] ?? ''),
        'Reply with the message only. No directive lines, no explanation.',
    ]);

    try {
        $response = $ai->complete($prompt);
        $body = trim($response->content);
    } catch (\Throwable $e) {
        $logger->error('Follow-up generation failed', ['followup_id' => (int) $row['id'], 'error' => $e->getMessage()]);
        $followups->markFailed((int) $row['id']);
        $failed++;
        continue;
    }

    if ($body === '') {
        $followups->markFailed((int) $row['id']);
        $failed++;
        continue;
    }

    $ok = $sender->send($channel, $connection, (string) ($row['contact_external_id'] ?? ''), $body);

    if (!$ok) {
        $followups->markFailed((int) $row['id']);
        $failed++;
        continue;
    }

    if ($conversationId > 0) {
        $conversations->storeMessage([
            'merchant_id' => $merchantId,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'direction' => 'out',
            'sender' => 'ai',
            'content_type' => 'text',
            'body' => $body,
            'ai_provider' => $response->provider,
            'ai_model' => $response->model,
            'ai_tokens_in' => $response->promptTokens,
            'ai_tokens_out' => $response->completionTokens,
            'ai_latency_ms' => $response->latencyMs,
            'status' => 'sent',
        ]);
        $conversations->touchConversation($conversationId);
    }

    $followups->markSent((int) $row['id']);
    $sent++;
}

$logger->info('Follow-up run complete', ['due' => count($due), 'sent' => $sent, 'failed' => $failed]);

echo sprintf("[%s] followups: due=%d sent=%d failed=%d\n", date('Y-m-d H:i:s'), count($due), $sent, $failed);
