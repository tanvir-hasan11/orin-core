<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Webhook;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\InboundMessageProcessor;

/**
 * WhatsApp Cloud API webhook.
 *
 * GET  - Meta verification handshake
 * POST - inbound messages
 */
final class WhatsAppWebhookController
{
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query['hub_mode'] ?? '');
        $token = (string) ($request->query['hub_verify_token'] ?? '');
        $challenge = (string) ($request->query['hub_challenge'] ?? '');
        $expected = (string) (getenv('WA_VERIFY_TOKEN') ?: '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return new Response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        app('logger')->warning('WhatsApp verification rejected', ['mode' => $mode]);

        return Response::html('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $secret = (string) (getenv('WA_APP_SECRET') ?: '');
        $signature = (string) ($request->header('X-Hub-Signature-256') ?? '');

        if ($secret !== '' && !$this->signatureIsValid($request->rawBody, $signature, $secret)) {
            app('logger')->warning('WhatsApp webhook signature mismatch');

            return Response::html('Forbidden', 403);
        }

        $payload = $request->body;
        /** @var InboundMessageProcessor $processor */
        $processor = app('inbound_processor');

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $accountId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $contactName = (string) ($value['contacts'][0]['profile']['name'] ?? '');

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $message = (array) $message;
                    $text = (string) ($message['text']['body'] ?? '');

                    if ($text === '') {
                        continue;
                    }

                    $processor->process([
                        'channel' => 'whatsapp',
                        'external_account_id' => $accountId,
                        'contact_external_id' => (string) ($message['from'] ?? ''),
                        'contact_name' => $contactName,
                        'message_id' => (string) ($message['id'] ?? ''),
                        'type' => (string) ($message['type'] ?? 'text'),
                        'text' => $text,
                        'raw' => $message,
                    ]);
                }
            }
        }

        return Response::json(['status' => 'ok']);
    }

    private function signatureIsValid(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
