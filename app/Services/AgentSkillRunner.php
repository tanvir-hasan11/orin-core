<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Support\Logger;
use Throwable;

/**
 * Executes the directives an agent emitted, honouring its autonomy level.
 *
 *   observe     - nothing is executed; the run is recorded as observed
 *   semi_auto   - lead changes apply, but orders and appointments are recorded
 *                 as pending and the thread is flagged for a human
 *   full_auto   - everything applies
 *
 * A skill the merchant switched off is never executed, no matter what the
 * model wrote.
 */
final class AgentSkillRunner
{
    /** @var array<string, string> directive kind => skill name */
    private const SKILL_FOR = [
        'order' => 'take_order',
        'appointment' => 'book_appointment',
        'followup' => 'schedule_followup',
        'catalogue' => 'share_catalogue',
        'location' => 'check_delivery_charge',
    ];

    /** @var array<int, string> */
    private const MONEY_ACTIONS = ['order', 'appointment'];

    public function __construct(
        private Database $db,
        private LeadService $leads,
        private ConversationService $conversations,
        private FollowUpService $followups,
        private Logger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, bool>  $skills
     * @return array<string, mixed>
     */
    public function run(ReplyParseResult $parsed, array $context, string $autonomy, array $skills): array
    {
        $outcome = [
            'stage' => false,
            'fields' => 0,
            'actions' => 0,
            'handoff' => false,
            'needs_human' => false,
            'observed' => false,
        ];

        if ($autonomy === 'observe') {
            $outcome['observed'] = true;

            return $outcome;
        }

        $merchantId = (int) ($context['merchant_id'] ?? 0);
        $agentId = isset($context['agent_id']) ? (int) $context['agent_id'] : null;
        $conversationId = isset($context['conversation_id']) ? (int) $context['conversation_id'] : null;
        $contactId = (int) ($context['contact_id'] ?? 0);
        $leadId = (int) ($context['lead_id'] ?? 0);
        $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
        $contact = is_array($context['contact'] ?? null) ? $context['contact'] : [];

        if ($parsed->stage !== null && ($skills['qualify_lead'] ?? false) === true) {
            $outcome['stage'] = $this->leads->applyStage($merchantId, $leadId, $parsed->stage, $profile);
        }

        if (($skills['capture_lead'] ?? false) === true) {
            foreach ($parsed->fields as $key => $value) {
                $this->leads->applyField($leadId, $key, $value, $contact);
                $outcome['fields']++;
            }
        }

        foreach ($parsed->actions as $action) {
            $kind = (string) $action['kind'];
            $payload = is_array($action['payload'] ?? null) ? $action['payload'] : [];

            $skill = self::SKILL_FOR[$kind] ?? null;
            if ($skill !== null && ($skills[$skill] ?? false) !== true) {
                $this->logger->warning('Agent emitted a directive for a disabled skill', [
                    'merchant_id' => $merchantId,
                    'kind' => $kind,
                    'skill' => $skill,
                ]);
                continue;
            }

            if ($kind === 'followup') {
                if ($this->scheduleFollowUp($merchantId, $agentId, $conversationId, $contactId, $payload)) {
                    $outcome['actions']++;
                }
                continue;
            }

            $isMoney = in_array($kind, self::MONEY_ACTIONS, true);
            $status = 'pending';

            if ($isMoney && $autonomy === 'full_auto') {
                $status = 'confirmed';
            } elseif ($isMoney) {
                $outcome['needs_human'] = true;
            }

            $this->recordAction($merchantId, $agentId, $conversationId, $contactId, $kind, $status, $payload);
            $outcome['actions']++;
        }

        if ($parsed->handoff) {
            $outcome['handoff'] = true;
            $outcome['needs_human'] = true;
        }

        if ($outcome['needs_human'] && $conversationId !== null) {
            $this->conversations->setHandoff($conversationId, 'requested');
        }

        return $outcome;
    }

    /** @param array<string, string> $payload */
    private function scheduleFollowUp(int $merchantId, ?int $agentId, ?int $conversationId, int $contactId, array $payload): bool
    {
        $delay = (string) ($payload['in'] ?? '');
        $dueAt = $this->followups->parseDelay($delay);

        if ($dueAt === null || $contactId === 0) {
            $this->logger->warning('Agent asked for an unusable follow-up delay', ['in' => $delay]);

            return false;
        }

        $reason = (string) ($payload['reason'] ?? 'the customer went quiet');

        $this->followups->schedule($merchantId, $agentId, $conversationId, $contactId, $reason, $dueAt);
        $this->recordAction($merchantId, $agentId, $conversationId, $contactId, 'followup', 'pending', [
            'in' => $delay,
            'reason' => $reason,
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /** @param array<string, string> $payload */
    private function recordAction(
        int $merchantId,
        ?int $agentId,
        ?int $conversationId,
        ?int $contactId,
        string $kind,
        string $status,
        array $payload,
    ): void {
        try {
            $now = date('Y-m-d H:i:s');

            $this->db->insert('agent_actions', [
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'contact_id' => $contactId,
                'kind' => $kind,
                'status' => $status,
                'title' => $this->titleFor($kind, $payload),
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Could not record agent action', [
                'merchant_id' => $merchantId,
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, string> $payload */
    private function titleFor(string $kind, array $payload): string
    {
        return match ($kind) {
            'order' => 'Order: ' . (string) ($payload['product'] ?? 'item')
                . (isset($payload['qty']) ? ' x' . $payload['qty'] : ''),
            'appointment' => 'Appointment: ' . trim((string) ($payload['date'] ?? '') . ' ' . (string) ($payload['time'] ?? ''))
                . (isset($payload['for']) ? ' for ' . $payload['for'] : ''),
            'followup' => 'Follow up in ' . (string) ($payload['in'] ?? '?'),
            'catalogue' => 'Catalogue requested' . (isset($payload['category']) ? ': ' . $payload['category'] : ''),
            'location' => 'Delivery area: ' . (string) ($payload['area'] ?? 'unknown'),
            default => ucfirst($kind),
        };
    }
}
