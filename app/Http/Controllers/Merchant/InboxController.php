<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\ConversationService;
use Orin\Services\OutboundSender;

/**
 * The merchant's live inbox: read the thread, approve a draft the agent wrote,
 * take over from the agent, reply.
 */
final class InboxController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');

        return $this->panel('merchant/inbox', [
            'title' => 'Inbox - Orin',
            'merchant' => $merchant,
            'conversations' => $conversations->listConversations((int) $merchant['id']),
        ]);
    }

    public function show(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $conversation = $conversations->findConversation((int) $merchant['id'], $id);

        if ($conversation === null) {
            return Response::html('<h1>Conversation not found</h1>', 404);
        }

        return $this->panel('merchant/conversation', [
            'title' => 'Conversation - Orin',
            'merchant' => $merchant,
            'conversation' => $conversation,
            'messages' => $conversations->messages($id),
            'notice' => app('session')->pullFlash('agent_notice'),
        ]);
    }

    public function reply(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $conversation = $conversations->findConversation((int) $merchant['id'], $id);
        $body = trim((string) $request->input('body', ''));

        if ($conversation === null || $body === '') {
            return Response::redirect('/merchant/inbox/' . $id);
        }

        $conversations->setHandoff($id, 'active');
        $sent = $this->sendToContact((int) $merchant['id'], $conversation, $body);

        $conversations->storeMessage([
            'merchant_id' => (int) $merchant['id'],
            'conversation_id' => $id,
            'direction' => 'out',
            'sender' => 'agent',
            'content_type' => 'text',
            'body' => $body,
            'status' => $sent ? 'sent' : 'failed',
        ]);
        $conversations->touchConversation($id);

        app('audit')->log('inbox.reply', 'conversation', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['sent' => $sent]);

        return Response::redirect('/merchant/inbox/' . $id);
    }

    /** Approve the draft the agent wrote in observe mode and send it. */
    public function approveDraft(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $conversation = $conversations->findConversation((int) $merchant['id'], $id);

        if ($conversation === null) {
            return Response::redirect('/merchant/inbox');
        }

        /** @var Database $db */
        $db = app('db');
        $draft = $db->first(
            "SELECT id, body FROM messages
             WHERE conversation_id = :c AND merchant_id = :m AND status = 'draft'
             ORDER BY id DESC LIMIT 1",
            ['c' => $id, 'm' => (int) $merchant['id']]
        );

        if ($draft === null) {
            app('session')->flash('agent_notice', 'There is no draft waiting on this conversation.');

            return Response::redirect('/merchant/inbox/' . $id);
        }

        $body = trim((string) $request->input('body', (string) $draft['body']));
        $sent = $this->sendToContact((int) $merchant['id'], $conversation, $body);

        $db->execute(
            'UPDATE messages SET body = :b, status = :s WHERE id = :id AND merchant_id = :m',
            [
                'b' => $body,
                's' => $sent ? 'sent' : 'failed',
                'id' => (int) $draft['id'],
                'm' => (int) $merchant['id'],
            ]
        );

        app('audit')->log('inbox.approve_draft', 'conversation', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['sent' => $sent]);

        return Response::redirect('/merchant/inbox/' . $id);
    }

    public function handoff(Request $request): Response
    {
        return $this->setHandoff($request, 'active');
    }

    public function resumeAi(Request $request): Response
    {
        return $this->setHandoff($request, 'none');
    }

    /** @param array<string, mixed> $conversation */
    private function sendToContact(int $merchantId, array $conversation, string $body): bool
    {
        $connection = app('conversation_service')->connectionFor($merchantId, (string) $conversation['channel']);

        if ($connection === null) {
            return false;
        }

        /** @var OutboundSender $sender */
        $sender = app('outbound_sender');

        return $sender->send((string) $conversation['channel'], $connection, (string) $conversation['contact_external_id'], $body);
    }

    private function setHandoff(Request $request, string $status): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        if ($conversations->findConversation((int) $merchant['id'], $id) !== null) {
            $conversations->setHandoff($id, $status);
            app('audit')->log('inbox.handoff', 'conversation', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['status' => $status]);
        }

        return Response::redirect('/merchant/inbox/' . $id);
    }
}
