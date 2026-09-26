<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\ConversationService;
use Orin\Services\OutboundSender;

/**
 * The merchant's live inbox: read the thread, take over from the AI, reply.
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

        $connection = $conversations->connectionFor((int) $merchant['id'], (string) $conversation['channel']);
        $sent = false;

        if ($connection !== null) {
            /** @var OutboundSender $sender */
            $sender = app('outbound_sender');
            $sent = $sender->send((string) $conversation['channel'], $connection, (string) $conversation['contact_external_id'], $body);
        }

        $conversations->storeMessage([
            'merchant_id' => (int) $merchant['id'],
            'conversation_id' => $id,
            'direction' => 'out',
            'sender' => 'agent',
            'content_type' => 'text',
            'body' => $body,
        ]);
        $conversations->touchConversation($id);

        app('audit')->log('inbox.reply', 'conversation', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['sent' => $sent]);

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

    private function setHandoff(Request $request, string $status): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ConversationService $conversations */
        $conversations = app('conversation_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        $conversation = $conversations->findConversation((int) $merchant['id'], $id);
        if ($conversation !== null) {
            $conversations->setHandoff($id, $status);
            app('audit')->log('inbox.handoff', 'conversation', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['status' => $status]);
        }

        return Response::redirect('/merchant/inbox/' . $id);
    }
}
