<?php

declare(strict_types=1);

namespace Orin\Channels;

use Orin\Core\Crypto;
use Orin\Support\Http\HttpClient;

/**
 * WhatsApp Cloud API sender.
 */
final class WhatsAppClient
{
    public function __construct(private HttpClient $http, private Crypto $crypto)
    {
    }

    /** @param array<string, mixed> $connection */
    public function sendText(array $connection, string $to, string $text): bool
    {
        $token = $this->crypto->decrypt((string) ($connection['access_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('WhatsApp access token is missing for this connection.');
        }

        $phoneNumberId = (string) $connection['external_account_id'];
        $version = getenv('WA_API_VERSION') ?: 'v21.0';

        $response = $this->http->postJson(
            'https://graph.facebook.com/' . $version . '/' . $phoneNumberId . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $text],
            ],
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ]
        );

        return $response->isSuccessful();
    }
}
