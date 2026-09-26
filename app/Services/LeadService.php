<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;

/**
 * Every contact that messages a merchant becomes a lead. The AI moves the lead
 * along the merchant's own pipeline by emitting a directive.
 */
final class LeadService
{
    public function __construct(
        private Database $db,
        private VerticalRegistry $verticals,
        private AuditService $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function ensureForContact(int $merchantId, int $contactId, int $conversationId, string $source): array
    {
        $existing = $this->db->first(
            'SELECT * FROM leads WHERE merchant_id = :m AND contact_id = :c LIMIT 1',
            ['m' => $merchantId, 'c' => $contactId]
        );

        if ($existing !== null) {
            if ((int) ($existing['conversation_id'] ?? 0) !== $conversationId) {
                $this->db->update('leads', [
                    'conversation_id' => $conversationId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $existing['id']]);
            }

            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('leads', [
            'merchant_id' => $merchantId,
            'contact_id' => $contactId,
            'conversation_id' => $conversationId,
            'stage' => 'new',
            'source' => $source,
            'score' => 0,
            'captured_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->first('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $id]) ?? [];
    }

    /** @param array<string, mixed> $profile */
    public function applyStage(int $merchantId, int $leadId, string $stage, array $profile): bool
    {
        $type = (string) ($profile['business_type'] ?? 'generic');

        if (!$this->verticals->isValidStage($type, $stage)) {
            return false;
        }

        $changed = $this->db->execute(
            'UPDATE leads SET stage = :s, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['s' => $stage, 'u' => date('Y-m-d H:i:s'), 'id' => $leadId, 'm' => $merchantId]
        ) > 0;

        if ($changed) {
            $this->audit->log('lead.stage', 'lead', (string) $leadId, null, 'ai', ['stage' => $stage]);
        }

        return $changed;
    }

    /**
     * Mirror a field the AI learned onto the contact record.
     *
     * @param array<string, mixed> $contact
     */
    public function applyField(int $leadId, string $key, string $value, array $contact): void
    {
        $now = date('Y-m-d H:i:s');

        if ($key === 'interest') {
            $this->db->execute(
                'UPDATE leads SET interest = :v, updated_at = :u WHERE id = :id',
                ['v' => mb_substr($value, 0, 250), 'u' => $now, 'id' => $leadId]
            );

            return;
        }

        $contactId = (int) ($contact['id'] ?? 0);
        if ($contactId === 0 || !in_array($key, ['name', 'phone', 'email', 'address'], true)) {
            return;
        }

        $this->db->execute(
            'UPDATE contacts SET ' . $key . ' = :v, updated_at = :u WHERE id = :id',
            ['v' => mb_substr($value, 0, 250), 'u' => $now, 'id' => $contactId]
        );
        $this->db->execute('UPDATE leads SET updated_at = :u WHERE id = :id', ['u' => $now, 'id' => $leadId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId, ?string $stage = null): array
    {
        $sql = 'SELECT l.id, l.stage, l.source, l.interest, l.score, l.created_at, l.updated_at,
                       c.id AS contact_id, c.name, c.phone, c.address, c.channel
                FROM leads l
                JOIN contacts c ON c.id = l.contact_id
                WHERE l.merchant_id = :m';

        $params = ['m' => $merchantId];

        if ($stage !== null && $stage !== '') {
            $sql .= ' AND l.stage = :stage';
            $params['stage'] = $stage;
        }

        $sql .= ' ORDER BY l.updated_at DESC, l.id DESC LIMIT 200';

        return $this->db->select($sql, $params);
    }

    /** @return array<string, int> */
    public function countsByStage(int $merchantId): array
    {
        $rows = $this->db->select(
            'SELECT stage, COUNT(*) AS total FROM leads WHERE merchant_id = :m GROUP BY stage',
            ['m' => $merchantId]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['stage']] = (int) $row['total'];
        }

        return $counts;
    }

    public function find(int $merchantId, int $leadId): ?array
    {
        return $this->db->first(
            'SELECT * FROM leads WHERE id = :id AND merchant_id = :m LIMIT 1',
            ['id' => $leadId, 'm' => $merchantId]
        );
    }

    public function moveStage(int $merchantId, int $leadId, string $stage, int $actorUserId): bool
    {
        $profile = $this->db->first('SELECT business_type FROM merchant_profiles WHERE merchant_id = :m LIMIT 1', ['m' => $merchantId]) ?? [];
        $type = (string) ($profile['business_type'] ?? 'generic');

        if (!$this->verticals->isValidStage($type, $stage)) {
            return false;
        }

        $changed = $this->db->execute(
            'UPDATE leads SET stage = :s, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['s' => $stage, 'u' => date('Y-m-d H:i:s'), 'id' => $leadId, 'm' => $merchantId]
        ) > 0;

        if ($changed) {
            $this->audit->log('lead.stage', 'lead', (string) $leadId, $actorUserId, 'merchant', ['stage' => $stage]);
        }

        return $changed;
    }
}
