<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Channels\MessengerClient;
use Orin\Channels\WhatsAppClient;
use Orin\Support\Logger;

/**
 * Picks the right channel client and records failures on the connection so the
 * merchant can see that their token went bad.
 */
final class OutboundSender
{
    public function __construct(
        private WhatsAppClient $whatsapp,
        private MessengerClient $messenger,
        private ChannelService $channels,
        private Logger $logger,
    ) {
    }

    /** @param array<string, mixed> $connection */
    public function send(string $channel, array $connection, string $to, string $text): bool
    {
        if ($text === '' || $to === '') {
            return false;
        }

        try {
            $ok = match ($channel) {
                'whatsapp' => $this->whatsapp->sendText($connection, $to, $text),
                'messenger', 'instagram' => $this->messenger->sendText($connection, $to, $text),
                default => false,
            };
        } catch (\Throwable $e) {
            $this->logger->error('Outbound send failed', [
                'channel' => $channel,
                'connection_id' => (int) ($connection['id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
            $this->channels->setError((int) ($connection['id'] ?? 0), $e->getMessage());

            return false;
        }

        if (!$ok) {
            $this->channels->setError((int) ($connection['id'] ?? 0), 'Channel API rejected the message.');
        }

        return $ok;
    }
}
