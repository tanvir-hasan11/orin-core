<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;

/**
 * What the merchant has taught their agent.
 *
 * Entries with agent_id = NULL are shared by every agent of that merchant.
 * Search is MySQL FULLTEXT over title, body and keywords. When the query is
 * too short or too noisy for FULLTEXT, the newest entries by kind are used as
 * a fallback so the agent always has something grounded to look at.
 */
final class KnowledgeService
{
    public const KINDS = [
        'about' => 'About the business',
        'faq' => 'Frequently asked question',
        'policy' => 'Policy (returns, warranty, refund)',
        'shipping' => 'Delivery and shipping',
        'payment' => 'Payment methods',
        'product_info' => 'Product or service details',
        'custom' => 'Something else',
    ];

    private const KIND_PRIORITY = "FIELD(kind,'about','faq','policy','shipping','payment','product_info','custom')";

    public function __construct(
        private Database $db,
        private AuditService $audit,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId, ?int $agentId = null): array
    {
        $params = ['m' => $merchantId];
        $sql = 'SELECT k.*, a.name AS agent_name
                FROM agent_knowledge k
                LEFT JOIN agents a ON a.id = k.agent_id
                WHERE k.merchant_id = :m';

        if ($agentId !== null) {
            $sql .= ' AND (k.agent_id IS NULL OR k.agent_id = :a)';
            $params['a'] = $agentId;
        }

        $sql .= ' ORDER BY k.is_active DESC, k.agent_id IS NOT NULL, k.updated_at DESC LIMIT 300';

        return $this->db->select($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function find(int $merchantId, int $entryId): ?array
    {
        return $this->db->first(
            'SELECT * FROM agent_knowledge WHERE id = :id AND merchant_id = :m LIMIT 1',
            ['id' => $entryId, 'm' => $merchantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(int $merchantId, array $data, int $actorUserId): int
    {
        $now = date('Y-m-d H:i:s');
        $kind = array_key_exists((string) ($data['kind'] ?? ''), self::KINDS) ? (string) $data['kind'] : 'faq';

        $id = $this->db->insert('agent_knowledge', [
            'merchant_id' => $merchantId,
            'agent_id' => !empty($data['agent_id']) ? (int) $data['agent_id'] : null,
            'kind' => $kind,
            'title' => mb_substr(trim((string) ($data['title'] ?? 'Untitled')), 0, 180),
            'body' => trim((string) ($data['body'] ?? '')),
            'keywords' => $this->nullable(mb_substr(trim((string) ($data['keywords'] ?? '')), 0, 490)),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit->log('knowledge.create', 'agent_knowledge', (string) $id, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
            'kind' => $kind,
        ]);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $merchantId, int $entryId, array $data, int $actorUserId): bool
    {
        $entry = $this->find($merchantId, $entryId);
        if ($entry === null) {
            return false;
        }

        $kind = (string) ($data['kind'] ?? $entry['kind']);
        if (!array_key_exists($kind, self::KINDS)) {
            $kind = 'faq';
        }

        $this->db->update('agent_knowledge', [
            'agent_id' => !empty($data['agent_id']) ? (int) $data['agent_id'] : null,
            'kind' => $kind,
            'title' => mb_substr(trim((string) ($data['title'] ?? $entry['title'])), 0, 180),
            'body' => trim((string) ($data['body'] ?? $entry['body'])),
            'keywords' => $this->nullable(mb_substr(trim((string) ($data['keywords'] ?? '')), 0, 490)),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $entryId, 'merchant_id' => $merchantId]);

        $this->audit->log('knowledge.update', 'agent_knowledge', (string) $entryId, $actorUserId, 'merchant');

        return true;
    }

    public function toggle(int $merchantId, int $entryId, int $actorUserId): bool
    {
        $entry = $this->find($merchantId, $entryId);
        if ($entry === null) {
            return false;
        }

        $enabled = (int) $entry['is_active'] === 1 ? 0 : 1;

        $this->db->execute(
            'UPDATE agent_knowledge SET is_active = :e, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['e' => $enabled, 'u' => date('Y-m-d H:i:s'), 'id' => $entryId, 'm' => $merchantId]
        );

        $this->audit->log('knowledge.toggle', 'agent_knowledge', (string) $entryId, $actorUserId, 'merchant', ['active' => $enabled]);

        return true;
    }

    public function delete(int $merchantId, int $entryId, int $actorUserId): bool
    {
        $deleted = $this->db->execute(
            'DELETE FROM agent_knowledge WHERE id = :id AND merchant_id = :m',
            ['id' => $entryId, 'm' => $merchantId]
        ) > 0;

        if ($deleted) {
            $this->audit->log('knowledge.delete', 'agent_knowledge', (string) $entryId, $actorUserId, 'merchant');
        }

        return $deleted;
    }

    /**
     * The entries most likely to matter for one customer message.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(int $merchantId, ?int $agentId, string $query, int $limit = 4): array
    {
        $limit = max(1, min(10, $limit));

        $scope = 'k.merchant_id = :m AND k.is_active = 1';
        $params = ['m' => $merchantId];

        if ($agentId !== null) {
            $scope .= ' AND (k.agent_id IS NULL OR k.agent_id = :a)';
            $params['a'] = $agentId;
        }

        $terms = $this->booleanTerms($query);

        if ($terms !== '') {
            $params['q'] = $terms;
            $rows = $this->db->select(
                'SELECT k.id, k.kind, k.title, k.body
                 FROM agent_knowledge k
                 WHERE ' . $scope . ' AND MATCH(k.title, k.body, k.keywords) AGAINST (:q IN BOOLEAN MODE)
                 ORDER BY MATCH(k.title, k.body, k.keywords) AGAINST (:q IN BOOLEAN MODE) DESC
                 LIMIT ' . $limit,
                $params
            );

            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->db->select(
            'SELECT k.id, k.kind, k.title, k.body
             FROM agent_knowledge k
             WHERE ' . $scope . '
             ORDER BY ' . self::KIND_PRIORITY . ', k.updated_at DESC
             LIMIT ' . $limit,
            $params
        );
    }

    private function booleanTerms(string $query): string
    {
        $words = preg_split('/\s+/u', mb_strtolower($query)) ?: [];
        $terms = [];

        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '';
            if (mb_strlen($word) < 3) {
                continue;
            }
            $terms[] = '+' . $word . '*';
            if (count($terms) >= 8) {
                break;
            }
        }

        return implode(' ', $terms);
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
