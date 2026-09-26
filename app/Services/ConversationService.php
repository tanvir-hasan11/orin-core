<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;

/**
 * Contacts, conversations and the message log.
 */
final class ConversationService
{
    public function __construct(private Database $db)
    {
    }

    public function hasMessage(int $merchantId, string $externalId): bool
    {
        if ($externalId === '') {
            return false;
        }

        $row = $this->db->first(
            'SELECT id FROM messages WHERE merchant_id = :m AND external_id = :e LIMIT 1',
            ['m' => $merchantId, 'e' => $externalId]
        );

        return $row !== null;
    }

    /** @return array<string, mixed> */
    public function upsertContact(int $merchantId, string $channel, string $externalId, string $name = ''): array
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->first(
            'SELECT * FROM contacts WHERE merchant_id = :m AND channel = :ch AND external_id = :e LIMIT 1',
            ['m' => $merchantId, 'ch' => $channel, 'e' => $externalId]
        );

        if ($existing !== null) {
            $update = ['last_seen_at' => $now, 'updated_at' => $now];
            if ($name !== '' && (string) ($existing['name'] ?? '') === '') {
                $update['name'] = $name;
            }
            $this->db->update('contacts', $update, ['id' => (int) $existing['id']]);

            return array_merge($existing, $update);
        }

        $id = $this->db->insert('contacts', [
            'merchant_id' => $merchantId,
            'channel' => $channel,
            'external_id' => $externalId,
            'name' => $name !== '' ? $name : null,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->first('SELECT * FROM contacts WHERE id = :id LIMIT 1', ['id' => $id]) ?? [];
    }

    /** @return array<string, mixed> */
    public function openConversation(int $merchantId, int $contactId, string $channel): array
    {
        $existing = $this->db->first(
            'SELECT * FROM conversations WHERE merchant_id = :m AND contact_id = :c AND channel = :ch AND status <> :closed ORDER BY id DESC LIMIT 1',
            ['m' => $merchantId, 'c' => $contactId, 'ch' => $channel, 'closed' => 'closed']
        );

        if ($existing !== null) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('conversations', [
            'merchant_id' => $merchantId,
            'contact_id' => $contactId,
            'channel' => $channel,
            'status' => 'open',
            'handoff_status' => 'none',
            'message_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->first('SELECT * FROM conversations WHERE id = :id LIMIT 1', ['id' => $id]) ?? [];
    }

    /** @param array<string, mixed> $data */
    public function storeMessage(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        return $this->db->insert('messages', $data);
    }

    public function touchConversation(int $conversationId): void
    {
        $this->db->execute(
            'UPDATE conversations SET message_count = message_count + 1, last_message_at = :n, updated_at = :n WHERE id = :id',
            ['n' => date('Y-m-d H:i:s'), 'id' => $conversationId]
        );
    }

    public function setHandoff(int $conversationId, string $status): void
    {
        $this->db->execute(
            'UPDATE conversations SET handoff_status = :s, updated_at = :u WHERE id = :id',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $conversationId]
        );
    }

    /** @return array<string, mixed> */
    public function profile(int $merchantId): array
    {
        $row = $this->db->first('SELECT * FROM merchant_profiles WHERE merchant_id = :m LIMIT 1', ['m' => $merchantId]);

        return $row ?? [
            'merchant_id' => $merchantId,
            'business_type' => 'generic',
            'tone' => 'friendly',
            'description' => null,
            'working_hours' => null,
            'delivery_info' => null,
            'service_area' => null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recentHistory(int $conversationId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));

        $rows = $this->db->select(
            'SELECT direction, sender, body FROM messages WHERE conversation_id = :c ORDER BY id DESC LIMIT ' . $limit,
            ['c' => $conversationId]
        );

        return array_reverse($rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function listConversations(int $merchantId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->db->select(
            'SELECT c.id, c.channel, c.status, c.handoff_status, c.message_count, c.last_message_at,
                    ct.id AS contact_id, ct.name AS contact_name, ct.phone AS contact_phone,
                    (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT m.direction FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_direction,
                    (SELECT l.stage FROM leads l WHERE l.conversation_id = c.id LIMIT 1) AS lead_stage
             FROM conversations c
             JOIN contacts ct ON ct.id = c.contact_id
             WHERE c.merchant_id = :m
             ORDER BY c.last_message_at DESC, c.id DESC
             LIMIT ' . $limit,
            ['m' => $merchantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findConversation(int $merchantId, int $conversationId): ?array
    {
        return $this->db->first(
            'SELECT c.*, ct.id AS contact_id, ct.name AS contact_name, ct.phone AS contact_phone,
                    ct.email AS contact_email, ct.address AS contact_address, ct.channel AS contact_channel,
                    ct.external_id AS contact_external_id
             FROM conversations c
             JOIN contacts ct ON ct.id = c.contact_id
             WHERE c.id = :id AND c.merchant_id = :m LIMIT 1',
            ['id' => $conversationId, 'm' => $merchantId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function messages(int $conversationId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return $this->db->select(
            'SELECT id, direction, sender, content_type, body, ai_provider, ai_model, created_at
             FROM messages WHERE conversation_id = :c ORDER BY id ASC LIMIT ' . $limit,
            ['c' => $conversationId]
        );
    }

    /** @return array<string, mixed>|null */
    public function connectionFor(int $merchantId, string $channel): ?array
    {
        return $this->db->first(
            'SELECT * FROM channel_connections WHERE merchant_id = :m AND channel = :ch AND status <> :off ORDER BY id ASC LIMIT 1',
            ['m' => $merchantId, 'ch' => $channel, 'off' => 'disconnected']
        );
    }
}
