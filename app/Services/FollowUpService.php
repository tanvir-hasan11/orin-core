<?php

declare(strict_types=1);

namespace Orin\Services;

use DateTimeImmutable;
use Orin\Core\Database;

/**
 * The agent can ask the system to come back to a customer later. This service
 * owns the queue; cron/followups.php drains it.
 */
final class FollowUpService
{
    public function __construct(private Database $db)
    {
    }

    /** "2d", "3h", "45m", "1w". Null when the value makes no sense. */
    public function parseDelay(string $value): ?DateTimeImmutable
    {
        $value = strtolower(trim($value));

        if (preg_match('/^(\d{1,3})\s*([mhdw])$/', $value, $m) !== 1) {
            return null;
        }

        $amount = (int) $m[1];
        if ($amount <= 0) {
            return null;
        }

        $seconds = match ($m[2]) {
            'm' => $amount * 60,
            'h' => $amount * 3600,
            'd' => $amount * 86400,
            'w' => $amount * 604800,
            default => 0,
        };

        if ($seconds === 0 || $seconds > 60 * 86400) {
            return null;
        }

        return (new DateTimeImmutable())->modify('+' . $seconds . ' seconds');
    }

    public function schedule(
        int $merchantId,
        ?int $agentId,
        ?int $conversationId,
        int $contactId,
        string $reason,
        DateTimeImmutable $dueAt,
    ): int {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('followups', [
            'merchant_id' => $merchantId,
            'agent_id' => $agentId,
            'conversation_id' => $conversationId,
            'contact_id' => $contactId,
            'reason' => mb_substr($reason, 0, 250),
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function due(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));

        return $this->db->select(
            'SELECT f.*, c.external_id AS contact_external_id, c.channel AS contact_channel
             FROM followups f
             JOIN contacts c ON c.id = f.contact_id
             WHERE f.status = :pending AND f.due_at <= :now
             ORDER BY f.due_at ASC LIMIT ' . $limit,
            ['pending' => 'pending', 'now' => date('Y-m-d H:i:s')]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));

        return $this->db->select(
            'SELECT f.*, c.name AS contact_name, c.phone AS contact_phone, c.channel AS contact_channel
             FROM followups f
             JOIN contacts c ON c.id = f.contact_id
             WHERE f.merchant_id = :m
             ORDER BY FIELD(f.status, :p, :s, :c, :fx), f.due_at DESC
             LIMIT ' . $limit,
            ['m' => $merchantId, 'p' => 'pending', 's' => 'sent', 'c' => 'cancelled', 'fx' => 'failed']
        );
    }

    public function markSent(int $followUpId): void
    {
        $this->db->execute(
            'UPDATE followups SET status = :s, sent_at = :n, updated_at = :n WHERE id = :id',
            ['s' => 'sent', 'n' => date('Y-m-d H:i:s'), 'id' => $followUpId]
        );
    }

    public function markFailed(int $followUpId): void
    {
        $this->db->execute(
            'UPDATE followups SET status = :s, updated_at = :u WHERE id = :id',
            ['s' => 'failed', 'u' => date('Y-m-d H:i:s'), 'id' => $followUpId]
        );
    }

    public function cancel(int $merchantId, int $followUpId, int $actorUserId): bool
    {
        return $this->db->execute(
            'UPDATE followups SET status = :s, updated_at = :u WHERE id = :id AND merchant_id = :m AND status = :p',
            [
                's' => 'cancelled',
                'u' => date('Y-m-d H:i:s'),
                'id' => $followUpId,
                'm' => $merchantId,
                'p' => 'pending',
            ]
        ) > 0;
    }

    public function pendingCount(int $merchantId): int
    {
        $row = $this->db->first(
            'SELECT COUNT(*) AS total FROM followups WHERE merchant_id = :m AND status = :p',
            ['m' => $merchantId, 'p' => 'pending']
        );

        return (int) ($row['total'] ?? 0);
    }
}
