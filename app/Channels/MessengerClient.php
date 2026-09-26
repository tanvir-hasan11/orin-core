<?php

declare(strict_types=1);

namespace Orin\Channels;

use Orin\Core\Crypto;
use Orin\Support\Http\HttpClient;

/**
 * Facebook Messenger (and Instagram) Send API sender.
 */
final class MessengerClient
{
    public function __construct(private HttpClient $http, private Crypto $crypto)
    {
    }

    /** @param array<string, mixed> $connection */
    public function sendText(array $connection, string $to, string $text): bool
    {
        $token = $this->crypto->decrypt((string) ($connection['access_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Page access token is missing for this connection.');
        }

        $version = getenv('FB_API_VERSION') ?: 'v21.0';

        $response = $this->http->postJson(
            'https://graph.facebook.com/' . $version . '/me/messages?access_token=' . urlencode($token),
            [
                'recipient' => ['id' => $to],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $text],
            ],
            ['Content-Type' => 'application/json']
        );

        return $response->isSuccessful();
    }
}
