<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Crypto;
use Orin\Core\Database;

/**
 * A merchant's connected WhatsApp numbers and Facebook pages.
 */
final class ChannelService
{
    public function __construct(
        private Database $db,
        private Crypto $crypto,
        private AuditService $audit,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findByAccount(string $channel, string $externalAccountId): ?array
    {
        return $this->db->first(
            'SELECT * FROM channel_connections WHERE channel = :ch AND external_account_id = :acc AND status <> :off LIMIT 1',
            ['ch' => $channel, 'acc' => $externalAccountId, 'off' => 'disconnected']
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId): array
    {
        return $this->db->select(
            'SELECT id, channel, external_account_id, display_name, status, last_error, connected_at, created_at
             FROM channel_connections WHERE merchant_id = :m ORDER BY channel, id',
            ['m' => $merchantId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function connect(int $merchantId, string $channel, array $data, int $actorUserId): int
    {
        $now = date('Y-m-d H:i:s');
        $account = (string) $data['external_account_id'];

        $existing = $this->db->first(
            'SELECT id FROM channel_connections WHERE channel = :ch AND external_account_id = :acc LIMIT 1',
            ['ch' => $channel, 'acc' => $account]
        );

        $values = [
            'merchant_id' => $merchantId,
            'channel' => $channel,
            'external_account_id' => $account,
            'display_name' => (string) ($data['display_name'] ?? '') ?: null,
            'access_token' => $this->crypto->encrypt((string) ($data['access_token'] ?? '')),
            'app_secret' => $this->crypto->encrypt((string) ($data['app_secret'] ?? '')),
            'verify_token' => (string) ($data['verify_token'] ?? '') ?: null,
            'status' => 'connected',
            'last_error' => null,
            'connected_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $id = (int) $existing['id'];
            $this->db->update('channel_connections', $values, ['id' => $id]);
        } else {
            $values['created_at'] = $now;
            $id = $this->db->insert('channel_connections', $values);
        }

        $this->audit->log('channel.connect', 'channel_connection', (string) $id, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
            'channel' => $channel,
        ]);

        return $id;
    }

    public function disconnect(int $merchantId, int $connectionId, int $actorUserId): bool
    {
        $changed = $this->db->execute(
            'UPDATE channel_connections SET status = :s, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['s' => 'disconnected', 'u' => date('Y-m-d H:i:s'), 'id' => $connectionId, 'm' => $merchantId]
        ) > 0;

        if ($changed) {
            $this->audit->log('channel.disconnect', 'channel_connection', (string) $connectionId, $actorUserId, 'merchant', [
                'merchant_id' => $merchantId,
            ]);
        }

        return $changed;
    }

    public function setError(int $connectionId, string $message): void
    {
        $this->db->execute(
            'UPDATE channel_connections SET status = :s, last_error = :e, updated_at = :u WHERE id = :id',
            ['s' => 'error', 'e' => mb_substr($message, 0, 490), 'u' => date('Y-m-d H:i:s'), 'id' => $connectionId]
        );
    }

    public function markHealthy(int $connectionId): void
    {
        $this->db->execute(
            'UPDATE channel_connections SET status = :s, last_error = NULL, updated_at = :u WHERE id = :id AND status <> :s',
            ['s' => 'connected', 'u' => date('Y-m-d H:i:s'), 'id' => $connectionId]
        );
    }
}
