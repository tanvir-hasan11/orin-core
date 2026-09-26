<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\KnowledgeService;

/**
 * What the merchant has taught their agent. Anything the agent is allowed to
 * state as fact should be reachable from here.
 */
final class KnowledgeController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var KnowledgeService $knowledge */
        $knowledge = app('knowledge_service');

        return $this->panel('merchant/knowledge', [
            'title' => 'Knowledge - Orin',
            'merchant' => $merchant,
            'entries' => $knowledge->listFor((int) $merchant['id']),
            'kinds' => KnowledgeService::KINDS,
            'agents' => app('agent_service')->listFor((int) $merchant['id']),
            'notice' => app('session')->pullFlash('agent_notice'),
        ]);
    }

    public function store(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));

        if ($title === '' || $body === '') {
            app('session')->flash('agent_notice', 'A knowledge entry needs both a title and content.');

            return Response::redirect('/merchant/knowledge');
        }

        app('knowledge_service')->create((int) $merchant['id'], [
            'agent_id' => $request->input('agent_id'),
            'kind' => (string) $request->input('kind', 'faq'),
            'title' => $title,
            'body' => $body,
            'keywords' => (string) $request->input('keywords', ''),
        ], (int) $request->attributes['user_id']);

        app('session')->flash('agent_notice', 'Knowledge entry added.');

        return Response::redirect('/merchant/knowledge');
    }

    public function update(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        app('knowledge_service')->update((int) $merchant['id'], $id, [
            'agent_id' => $request->input('agent_id'),
            'kind' => (string) $request->input('kind', 'faq'),
            'title' => (string) $request->input('title', ''),
            'body' => (string) $request->input('body', ''),
            'keywords' => (string) $request->input('keywords', ''),
        ], (int) $request->attributes['user_id']);

        app('session')->flash('agent_notice', 'Knowledge entry updated.');

        return Response::redirect('/merchant/knowledge');
    }

    public function toggle(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        app('knowledge_service')->toggle((int) $merchant['id'], $id, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/knowledge');
    }

    public function delete(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        app('knowledge_service')->delete((int) $merchant['id'], $id, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/knowledge');
    }
}
