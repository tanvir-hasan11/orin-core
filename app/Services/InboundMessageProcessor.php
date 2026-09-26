<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Support\Logger;
use Throwable;

/**
 * The heart of ORIN.
 *
 * One customer message in. The merchant's own agent answers, the lead moves,
 * whatever the agent decided to do actually happens, and a human is pulled in
 * the moment that is the right thing.
 *
 *   1. resolve the merchant from the channel account
 *   2. drop Meta retries
 *   3. upsert the contact, open a conversation, store the inbound message
 *   4. capture the lead
 *   5. resolve the merchant's agent; stop if it is off or paused
 *   6. if a human owns the thread, stop here
 *   7. build the agent prompt from identity, business, vertical, knowledge,
 *      skills, rules, customer and transcript
 *   8. call the platform AI chain
 *   9. strip directives, then either send or store as a draft (observe mode)
 *  10. execute directives through the skill runner, honouring autonomy
 *  11. record usage and the agent run
 */
final class InboundMessageProcessor
{
    public function __construct(
        private Database $db,
        private ChannelService $channels,
        private ConversationService $conversations,
        private LeadService $leads,
        private AgentService $agents,
        private KnowledgeService $knowledge,
        private AgentPromptBuilder $prompts,
        private AiGateway $ai,
        private ReplyDirectiveParser $parser,
        private AgentSkillRunner $skills,
        private OutboundSender $sender,
        private Logger $logger,
        private int $historyLimit = 12,
    ) {
    }

    /** @param array<string, mixed> $inbound */
    public function process(array $inbound): void
    {
        $channel = (string) ($inbound['channel'] ?? '');
        $accountId = (string) ($inbound['external_account_id'] ?? '');
        $fromId = (string) ($inbound['contact_external_id'] ?? '');
        $externalId = (string) ($inbound['message_id'] ?? '');
        $text = trim((string) ($inbound['text'] ?? ''));

        if ($channel === '' || $accountId === '' || $fromId === '') {
            return;
        }

        $connection = $this->channels->findByAccount($channel, $accountId);
        if ($connection === null) {
            $this->logger->warning('Inbound for an unknown channel account', [
                'channel' => $channel,
                'account' => $accountId,
            ]);

            return;
        }

        $merchantId = (int) $connection['merchant_id'];

        if ($this->conversations->hasMessage($merchantId, $externalId)) {
            return;
        }

        $contact = $this->conversations->upsertContact($merchantId, $channel, $fromId, (string) ($inbound['contact_name'] ?? ''));
        if ($contact === []) {
            return;
        }

        $conversation = $this->conversations->openConversation($merchantId, (int) $contact['id'], $channel);
        if ($conversation === []) {
            return;
        }

        $conversationId = (int) $conversation['id'];

        $this->conversations->storeMessage([
            'merchant_id' => $merchantId,
            'conversation_id' => $conversationId,
            'external_id' => $externalId !== '' ? $externalId : null,
            'direction' => 'in',
            'sender' => 'contact',
            'content_type' => (string) ($inbound['type'] ?? 'text'),
            'body' => $text,
            'raw_payload' => json_encode($inbound['raw'] ?? [], JSON_UNESCAPED_UNICODE) ?: null,
        ]);
        $this->conversations->touchConversation($conversationId);

        $lead = $this->leads->ensureForContact($merchantId, (int) $contact['id'], $conversationId, $channel);
        $leadId = (int) ($lead['id'] ?? 0);

        $agent = $this->agents->defaultFor($merchantId);
        if ($agent === null) {
            $this->logger->info('No active agent for this merchant; leaving the thread to a human', [
                'merchant_id' => $merchantId,
            ]);

            return;
        }

        $agentId = (int) $agent['id'];
        $autonomy = (string) $agent['autonomy'];

        if ($autonomy === 'off') {
            return;
        }

        $handoff = (string) ($conversation['handoff_status'] ?? 'none');
        if ($handoff === 'active' || $handoff === 'requested') {
            return;
        }

        if ($text === '') {
            return;
        }

        $profile = $this->conversations->profile($merchantId);
        $skills = $this->agents->skillsFor($merchantId, $agentId);
        $knowledge = $this->knowledge->search($merchantId, $agentId, $text, 4);
        $history = $this->conversations->recentHistory($conversationId, $this->historyLimit);

        $merchant = $this->db->first('SELECT company_name FROM merchants WHERE id = :id LIMIT 1', ['id' => $merchantId]) ?? [];

        $prompt = $this->prompts->build([
            'business_name' => (string) ($merchant['company_name'] ?? 'this business'),
            'profile' => $profile,
            'agent' => $agent,
            'skills' => $skills,
            'knowledge' => $knowledge,
            'contact' => $contact,
            'lead' => $lead,
            'history' => $history,
            'channel' => $channel,
        ]);

        try {
            $response = $this->ai->complete($prompt);
        } catch (Throwable $e) {
            $this->logger->error('Agent reply failed', [
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            $this->conversations->setHandoff($conversationId, 'requested');
            $this->recordRun($merchantId, $agentId, $conversationId, 'failed', [], 0, 0, 0, $e->getMessage());

            return;
        }

        $parsed = $this->parser->parse($response->content);
        $body = $parsed->text !== '' ? $parsed->text : 'Sorry, could you say that again?';

        // Observe mode: the reply is stored but never sent. A human approves it.
        if ($autonomy === 'observe') {
            $this->conversations->storeMessage([
                'merchant_id' => $merchantId,
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'direction' => 'out',
                'sender' => 'ai',
                'content_type' => 'text',
                'body' => $body,
                'ai_provider' => $response->provider,
                'ai_model' => $response->model,
                'ai_tokens_in' => $response->promptTokens,
                'ai_tokens_out' => $response->completionTokens,
                'ai_latency_ms' => $response->latencyMs,
                'status' => 'draft',
            ]);
            $this->conversations->touchConversation($conversationId);

            $this->recordUsage($merchantId, $response->provider, $response->model, $response->promptTokens, $response->completionTokens, $response->latencyMs, true);
            $this->recordRun($merchantId, $agentId, $conversationId, 'observed', $this->directiveSummary($parsed), $response->promptTokens, $response->completionTokens, $response->latencyMs, 'draft awaiting approval');

            return;
        }

        $sent = $this->sender->send($channel, $connection, $fromId, $body);

        $this->conversations->storeMessage([
            'merchant_id' => $merchantId,
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'direction' => 'out',
            'sender' => 'ai',
            'content_type' => 'text',
            'body' => $body,
            'ai_provider' => $response->provider,
            'ai_model' => $response->model,
            'ai_tokens_in' => $response->promptTokens,
            'ai_tokens_out' => $response->completionTokens,
            'ai_latency_ms' => $response->latencyMs,
            'status' => $sent ? 'sent' : 'failed',
        ]);
        $this->conversations->touchConversation($conversationId);

        $this->recordUsage($merchantId, $response->provider, $response->model, $response->promptTokens, $response->completionTokens, $response->latencyMs, $sent);

        $outcome = $this->skills->run($parsed, [
            'merchant_id' => $merchantId,
            'agent_id' => $agentId,
            'conversation_id' => $conversationId,
            'contact_id' => (int) $contact['id'],
            'lead_id' => $leadId,
            'profile' => $profile,
            'contact' => $contact,
        ], $autonomy, $skills);

        $decision = 'replied';
        if (!empty($outcome['handoff']) || !empty($outcome['needs_human'])) {
            $decision = 'handoff';
        }
        if (!$sent) {
            $decision = 'send_failed';
            $this->conversations->setHandoff($conversationId, 'requested');
        }

        $this->recordRun(
            $merchantId,
            $agentId,
            $conversationId,
            $decision,
            $this->directiveSummary($parsed) + ['outcome' => $outcome],
            $response->promptTokens,
            $response->completionTokens,
            $response->latencyMs,
            null
        );
    }

    /** @return array<string, mixed> */
    private function directiveSummary(ReplyParseResult $parsed): array
    {
        return [
            'stage' => $parsed->stage,
            'fields' => array_keys($parsed->fields),
            'handoff' => $parsed->handoff,
            'actions' => array_map(static fn (array $a): string => (string) $a['kind'], $parsed->actions),
        ];
    }

    /** @param array<string, mixed> $directive */
    private function recordRun(
        int $merchantId,
        ?int $agentId,
        ?int $conversationId,
        string $decision,
        array $directive,
        int $tokensIn,
        int $tokensOut,
        int $latencyMs,
        ?string $note,
    ): void {
        try {
            $this->db->insert('agent_runs', [
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'decision' => mb_substr($decision, 0, 30),
                'directive' => $directive === [] ? null : json_encode($directive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'latency_ms' => $latencyMs,
                'note' => $note === null ? null : mb_substr($note, 0, 490),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Could not record agent run', ['error' => $e->getMessage()]);
        }
    }

    private function recordUsage(int $merchantId, string $provider, string $model, int $tokensIn, int $tokensOut, int $latencyMs, bool $ok): void
    {
        $now = date('Y-m-d H:i:s');
        $period = date('Y-m');
        $cost = 0.0;

        try {
            $this->db->insert('usage_logs', [
                'merchant_id' => $merchantId,
                'api_key_id' => null,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $tokensIn,
                'completion_tokens' => $tokensOut,
                'cost_usd' => $cost,
                'latency_ms' => $latencyMs,
                'status' => $ok ? 'ok' : 'send_failed',
                'created_at' => $now,
            ]);

            $this->db->execute(
                'INSERT INTO usage_monthly (merchant_id, period, tokens_used, cost_usd, created_at, updated_at)
                 VALUES (:m, :p, :t, :c, :n, :n)
                 ON DUPLICATE KEY UPDATE tokens_used = tokens_used + VALUES(tokens_used), cost_usd = cost_usd + VALUES(cost_usd), updated_at = :n',
                ['m' => $merchantId, 'p' => $period, 't' => $tokensIn + $tokensOut, 'c' => $cost, 'n' => $now]
            );
        } catch (Throwable $e) {
            $this->logger->warning('Usage accounting failed', ['error' => $e->getMessage()]);
        }
    }
}
