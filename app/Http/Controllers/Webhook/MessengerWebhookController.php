<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Webhook;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\InboundMessageProcessor;

/**
 * Facebook Messenger webhook. Instagram uses the same payload shape.
 */
final class MessengerWebhookController
{
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query['hub_mode'] ?? '');
        $token = (string) ($request->query['hub_verify_token'] ?? '');
        $challenge = (string) ($request->query['hub_challenge'] ?? '');
        $expected = (string) (getenv('MS_VERIFY_TOKEN') ?: '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return new Response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        app('logger')->warning('Messenger verification rejected', ['mode' => $mode]);

        return Response::html('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $secret = (string) (getenv('FB_APP_SECRET') ?: '');
        $signature = (string) ($request->header('X-Hub-Signature-256') ?? '');

        if ($secret !== '' && !$this->signatureIsValid($request->rawBody, $signature, $secret)) {
            app('logger')->warning('Messenger webhook signature mismatch');

            return Response::html('Forbidden', 403);
        }

        $payload = $request->body;
        $object = (string) ($payload['object'] ?? 'page');
        $channel = $object === 'instagram' ? 'instagram' : 'messenger';

        /** @var InboundMessageProcessor $processor */
        $processor = app('inbound_processor');

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $entry = (array) $entry;
            $accountId = (string) ($entry['id'] ?? '');

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $event = (array) $event;
                $message = (array) ($event['message'] ?? []);

                if ($message === [] || !empty($message['is_echo'])) {
                    continue;
                }

                $text = (string) ($message['text'] ?? '');
                if ($text === '') {
                    continue;
                }

                $senderId = (string) ($event['sender']['id'] ?? '');

                $processor->process([
                    'channel' => $channel,
                    'external_account_id' => $accountId !== '' ? $accountId : (string) ($event['recipient']['id'] ?? ''),
                    'contact_external_id' => $senderId,
                    'contact_name' => '',
                    'message_id' => (string) ($message['mid'] ?? ''),
                    'type' => 'text',
                    'text' => $text,
                    'raw' => $event,
                ]);
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
