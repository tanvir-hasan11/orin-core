<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;

/**
 * The merchant's own AI agents.
 *
 * Each merchant owns one or more agents. Every agent has an identity, its own
 * knowledge, its own enabled skills, its own autonomy level and its own
 * escalation rules. The default agent answers anything that is not routed
 * elsewhere.
 */
final class AgentService
{
    /**
     * The capabilities an agent can be given.
     *
     * request_handoff is deliberately absent from this list: the agent must
     * always be able to reach a human, and a merchant cannot switch that off.
     */
    public const SKILLS = [
        'capture_lead' => ['label' => 'Capture the lead', 'detail' => 'Save the name, phone, address and interest the customer gives you.'],
        'qualify_lead' => ['label' => 'Move the lead along', 'detail' => 'Update the lead stage as the conversation progresses.'],
        'answer_from_knowledge' => ['label' => 'Answer from your knowledge', 'detail' => 'Use the business information, FAQs and policies you entered.'],
        'share_catalogue' => ['label' => 'Share the catalogue', 'detail' => 'Send the product or service list to the customer.'],
        'check_delivery_charge' => ['label' => 'Quote delivery', 'detail' => 'Tell the customer the delivery charge and the service area.'],
        'take_order' => ['label' => 'Take orders', 'detail' => 'Record a cash-on-delivery order for you to confirm.'],
        'book_appointment' => ['label' => 'Book appointments', 'detail' => 'Record a date, time and reason for the visit.'],
        'schedule_followup' => ['label' => 'Follow up later', 'detail' => 'Come back to a customer who went quiet.'],
        'check_order_status' => ['label' => 'Check order status', 'detail' => 'Tell the customer where their order is.'],
    ];

    public const ALWAYS_ON = ['request_handoff'];

    public const AUTONOMY = [
        'off' => 'Off - the agent stays silent, you answer everything',
        'observe' => 'Observe - the agent drafts a reply, you approve it before it sends',
        'semi_auto' => 'Semi-automatic - the agent replies, but orders and bookings wait for you',
        'full_auto' => 'Full automatic - the agent handles the conversation and records actions itself',
    ];

    public const TONES = [
        'friendly' => 'Friendly',
        'warm' => 'Warm and personal',
        'formal' => 'Formal',
        'concise' => 'Short and direct',
    ];

    public function __construct(
        private Database $db,
        private AuditService $audit,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId): array
    {
        return $this->db->select(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM agent_knowledge k WHERE k.merchant_id = a.merchant_id AND (k.agent_id IS NULL OR k.agent_id = a.id) AND k.is_active = 1) AS knowledge_count,
                    (SELECT COUNT(*) FROM agent_runs r WHERE r.agent_id = a.id) AS run_count
             FROM agents a
             WHERE a.merchant_id = :m
             ORDER BY a.is_default DESC, a.id ASC',
            ['m' => $merchantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $merchantId, int $agentId): ?array
    {
        return $this->db->first(
            'SELECT * FROM agents WHERE id = :id AND merchant_id = :m LIMIT 1',
            ['id' => $agentId, 'm' => $merchantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function defaultFor(int $merchantId): ?array
    {
        return $this->db->first(
            "SELECT * FROM agents WHERE merchant_id = :m AND status = 'active' ORDER BY is_default DESC, id ASC LIMIT 1",
            ['m' => $merchantId]
        );
    }

    /**
     * Guarantee the merchant has an agent, so a fresh account is never stuck
     * with an empty inbox.
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function ensureDefault(int $merchantId, array $profile = []): array
    {
        $existing = $this->defaultFor($merchantId);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->create($merchantId, [
            'name' => 'Sales Assistant',
            'role_label' => 'Sales Assistant',
            'autonomy' => 'semi_auto',
            'tone' => (string) ($profile['tone'] ?? 'friendly'),
            'is_default' => true,
        ], 0);

        return $this->find($merchantId, $id) ?? [];
    }

    /** @param array<string, mixed> $data */
    public function create(int $merchantId, array $data, int $actorUserId): int
    {
        $now = date('Y-m-d H:i:s');

        $agentId = $this->db->insert('agents', [
            'merchant_id' => $merchantId,
            'name' => $this->cleanName((string) ($data['name'] ?? 'Assistant')),
            'role_label' => mb_substr((string) ($data['role_label'] ?? 'Sales Assistant'), 0, 110),
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'status' => 'active',
            'autonomy' => $this->cleanAutonomy((string) ($data['autonomy'] ?? 'semi_auto')),
            'tone' => $this->cleanTone((string) ($data['tone'] ?? 'friendly')),
            'language' => (string) ($data['language'] ?? 'auto'),
            'system_instructions' => $this->nullable((string) ($data['system_instructions'] ?? '')),
            'escalation_rules' => $this->nullable((string) ($data['escalation_rules'] ?? '')),
            'after_hours_message' => $this->nullable((string) ($data['after_hours_message'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->seedSkills($merchantId, $agentId);

        if (!empty($data['is_default'])) {
            $this->makeDefault($merchantId, $agentId, $actorUserId);
        }

        $this->audit->log('agent.create', 'agent', (string) $agentId, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
        ]);

        return $agentId;
    }

    /** @param array<string, mixed> $data */
    public function update(int $merchantId, int $agentId, array $data, int $actorUserId): bool
    {
        $agent = $this->find($merchantId, $agentId);
        if ($agent === null) {
            return false;
        }

        $this->db->update('agents', [
            'name' => $this->cleanName((string) ($data['name'] ?? $agent['name'])),
            'role_label' => mb_substr((string) ($data['role_label'] ?? $agent['role_label']), 0, 110),
            'autonomy' => $this->cleanAutonomy((string) ($data['autonomy'] ?? $agent['autonomy'])),
            'tone' => $this->cleanTone((string) ($data['tone'] ?? $agent['tone'])),
            'language' => (string) ($data['language'] ?? $agent['language']),
            'system_instructions' => $this->nullable((string) ($data['system_instructions'] ?? '')),
            'escalation_rules' => $this->nullable((string) ($data['escalation_rules'] ?? '')),
            'after_hours_message' => $this->nullable((string) ($data['after_hours_message'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $agentId, 'merchant_id' => $merchantId]);

        $this->audit->log('agent.update', 'agent', (string) $agentId, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
        ]);

        return true;
    }

    public function makeDefault(int $merchantId, int $agentId, int $actorUserId): bool
    {
        if ($this->find($merchantId, $agentId) === null) {
            return false;
        }

        $this->db->execute('UPDATE agents SET is_default = 0 WHERE merchant_id = :m', ['m' => $merchantId]);
        $this->db->execute(
            "UPDATE agents SET is_default = 1, status = 'active', updated_at = :u WHERE id = :id AND merchant_id = :m",
            ['u' => date('Y-m-d H:i:s'), 'id' => $agentId, 'm' => $merchantId]
        );

        $this->audit->log('agent.default', 'agent', (string) $agentId, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
        ]);

        return true;
    }

    public function setStatus(int $merchantId, int $agentId, string $status, int $actorUserId): bool
    {
        if (!in_array($status, ['active', 'paused'], true)) {
            return false;
        }

        $changed = $this->db->execute(
            'UPDATE agents SET status = :s, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $agentId, 'm' => $merchantId]
        ) > 0;

        if ($changed) {
            $this->audit->log('agent.status', 'agent', (string) $agentId, $actorUserId, 'merchant', ['status' => $status]);
        }

        return $changed;
    }

    /**
     * @return array<string, bool>
     */
    public function skillsFor(int $merchantId, int $agentId): array
    {
        $skills = [];
        foreach (array_keys(self::SKILLS) as $skill) {
            $skills[$skill] = true;
        }

        $rows = $this->db->select(
            'SELECT skill, enabled FROM agent_skills WHERE agent_id = :a AND merchant_id = :m',
            ['a' => $agentId, 'm' => $merchantId]
        );

        foreach ($rows as $row) {
            $skills[(string) $row['skill']] = (int) $row['enabled'] === 1;
        }

        foreach (self::ALWAYS_ON as $skill) {
            $skills[$skill] = true;
        }

        return $skills;
    }

    /** @param array<string, mixed> $selected skill => truthy */
    public function saveSkills(int $merchantId, int $agentId, array $selected, int $actorUserId): void
    {
        if ($this->find($merchantId, $agentId) === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (array_keys(self::SKILLS) as $skill) {
            $this->upsertSkill($merchantId, $agentId, $skill, !empty($selected[$skill]) ? 1 : 0, $now);
        }
        foreach (self::ALWAYS_ON as $skill) {
            $this->upsertSkill($merchantId, $agentId, $skill, 1, $now);
        }

        $this->audit->log('agent.skills', 'agent', (string) $agentId, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
        ]);
    }

    private function upsertSkill(int $merchantId, int $agentId, string $skill, int $enabled, string $now): void
    {
        $this->db->execute(
            'INSERT INTO agent_skills (merchant_id, agent_id, skill, enabled, created_at, updated_at)
             VALUES (:m, :a, :s, :e, :n, :n)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = :n',
            ['m' => $merchantId, 'a' => $agentId, 's' => $skill, 'e' => $enabled, 'n' => $now]
        );
    }

    private function seedSkills(int $merchantId, int $agentId): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (array_keys(self::SKILLS) as $skill) {
            $this->upsertSkill($merchantId, $agentId, $skill, 1, $now);
        }
        foreach (self::ALWAYS_ON as $skill) {
            $this->upsertSkill($merchantId, $agentId, $skill, 1, $now);
        }
    }

    /** @return array<string, int> */
    public function stats(int $merchantId, int $agentId): array
    {
        $runs = $this->db->first('SELECT COUNT(*) AS total FROM agent_runs WHERE agent_id = :a', ['a' => $agentId]);
        $replied = $this->db->first("SELECT COUNT(*) AS total FROM agent_runs WHERE agent_id = :a AND decision = 'replied'", ['a' => $agentId]);
        $handoffs = $this->db->first("SELECT COUNT(*) AS total FROM agent_runs WHERE agent_id = :a AND decision = 'handoff'", ['a' => $agentId]);
        $actions = $this->db->first('SELECT COUNT(*) AS total FROM agent_actions WHERE agent_id = :a', ['a' => $agentId]);

        return [
            'runs' => (int) ($runs['total'] ?? 0),
            'replied' => (int) ($replied['total'] ?? 0),
            'handoffs' => (int) ($handoffs['total'] ?? 0),
            'actions' => (int) ($actions['total'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recentRuns(int $merchantId, int $agentId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));

        return $this->db->select(
            'SELECT id, conversation_id, decision, tokens_in, tokens_out, latency_ms, note, created_at
             FROM agent_runs WHERE merchant_id = :m AND agent_id = :a
             ORDER BY id DESC LIMIT ' . $limit,
            ['m' => $merchantId, 'a' => $agentId]
        );
    }

    public function cleanAutonomy(string $value): string
    {
        return array_key_exists($value, self::AUTONOMY) ? $value : 'semi_auto';
    }

    public function cleanTone(string $value): string
    {
        return array_key_exists($value, self::TONES) ? $value : 'friendly';
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'Assistant' : mb_substr($name, 0, 110);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
