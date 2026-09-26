<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Support\Logger;
use Throwable;

/**
 * The heart of ORIN.
 *
 * One customer message in, one AI reply out, a lead captured on the way.
 *
 *   1. resolve the merchant from the channel account
 *   2. drop Meta retries
 *   3. upsert the contact, open a conversation, store the inbound message
 *   4. capture the lead
 *   5. if a human owns the thread, stop here
 *   6. build the vertical-aware prompt, call the platform AI chain
 *   7. strip machine directives, send the clean text back
 *   8. apply directives: lead stage, captured fields, handoff
 */
final class InboundMessageProcessor
{
    public function __construct(
        private Database $db,
        private ChannelService $channels,
        private ConversationService $conversations,
        private LeadService $leads,
        private VerticalPromptBuilder $prompts,
        private AiGateway $ai,
        private ReplyDirectiveParser $parser,
        private OutboundSender $sender,
        private Logger $logger,
        private int $historyLimit = 12,
    ) {
    }

    /**
     * @param array<string, mixed> $inbound
     */
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

        $handoff = (string) ($conversation['handoff_status'] ?? 'none');
        if ($handoff === 'active' || $handoff === 'requested') {
            return;
        }

        if ($text === '') {
            return;
        }

        $profile = $this->conversations->profile($merchantId);
        $history = $this->conversations->recentHistory($conversationId, $this->historyLimit);

        $merchant = $this->db->first('SELECT company_name FROM merchants WHERE id = :id LIMIT 1', ['id' => $merchantId]) ?? [];

        $prompt = $this->prompts->build([
            'business_name' => (string) ($merchant['company_name'] ?? 'this business'),
            'profile' => $profile,
            'contact' => $contact,
            'lead' => $lead,
            'history' => $history,
        ]);

        try {
            $response = $this->ai->complete($prompt);
        } catch (Throwable $e) {
            $this->logger->error('AI reply failed', [
                'merchant_id' => $merchantId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            $this->conversations->setHandoff($conversationId, 'requested');

            return;
        }

        $parsed = $this->parser->parse($response->content);
        $body = $parsed->text !== '' ? $parsed->text : 'Sorry, could you say that again?';

        $sent = $this->sender->send($channel, $connection, $fromId, $body);

        $this->conversations->storeMessage([
            'merchant_id' => $merchantId,
            'conversation_id' => $conversationId,
            'direction' => 'out',
            'sender' => 'ai',
            'content_type' => 'text',
            'body' => $body,
            'ai_provider' => $response->provider,
            'ai_model' => $response->model,
            'ai_tokens_in' => $response->promptTokens,
            'ai_tokens_out' => $response->completionTokens,
            'ai_latency_ms' => $response->latencyMs,
        ]);
        $this->conversations->touchConversation($conversationId);

        $this->recordUsage($merchantId, $response->provider, $response->model, $response->promptTokens, $response->completionTokens, $response->latencyMs, $sent);

        if ($parsed->handoff) {
            $this->conversations->setHandoff($conversationId, 'requested');
        }

        if ($parsed->stage !== null) {
            $this->leads->applyStage($merchantId, (int) ($lead['id'] ?? 0), $parsed->stage, $profile);
        }

        foreach ($parsed->fields as $key => $value) {
            $this->leads->applyField((int) ($lead['id'] ?? 0), $key, $value, $contact);
        }

        if (!$sent) {
            $this->conversations->setHandoff($conversationId, 'requested');
        }
    }

    private function recordUsage(int $merchantId, string $provider, string $model, int $tokensIn, int $tokensOut, int $latencyMs, bool $ok): void
    {
        $now = date('Y-m-d H:i:s');
        $period = date('Y-m');
        $cost = 0.0;

        $prices = $this->db->first(
            'SELECT default_model FROM platform_providers WHERE provider = :p LIMIT 1',
            ['p' => $provider]
        );
        unset($prices);

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
