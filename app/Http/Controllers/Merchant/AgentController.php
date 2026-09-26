<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\AgentService;

/**
 * Where a merchant builds their own agent: who it is, what it knows, what it
 * is allowed to do, and how much it may do on its own.
 */
final class AgentController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var AgentService $agents */
        $agents = app('agent_service');
        $agents->ensureDefault((int) $merchant['id'], app('conversation_service')->profile((int) $merchant['id']));

        return $this->panel('merchant/agents', [
            'title' => 'AI Agents - Orin',
            'merchant' => $merchant,
            'agents' => $agents->listFor((int) $merchant['id']),
            'autonomyLevels' => AgentService::AUTONOMY,
            'maxAgents' => (int) ($merchant['max_agents'] ?? 1),
            'notice' => app('session')->pullFlash('agent_notice'),
        ]);
    }

    public function create(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var AgentService $agents */
        $agents = app('agent_service');
        $existing = $agents->listFor((int) $merchant['id']);

        if (count($existing) >= (int) ($merchant['max_agents'] ?? 1)) {
            app('session')->flash('agent_notice', 'Your plan allows fewer agents than you asked for. Upgrade to add another.');

            return Response::redirect('/merchant/agents');
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            app('session')->flash('agent_notice', 'Give the agent a name first.');

            return Response::redirect('/merchant/agents');
        }

        $id = $agents->create((int) $merchant['id'], [
            'name' => $name,
            'role_label' => (string) $request->input('role_label', 'Sales Assistant'),
            'autonomy' => (string) $request->input('autonomy', 'semi_auto'),
            'tone' => (string) $request->input('tone', 'friendly'),
            'is_default' => $existing === [],
        ], (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/agents/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var AgentService $agents */
        $agents = app('agent_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $agent = $agents->find((int) $merchant['id'], $id);

        if ($agent === null) {
            return Response::html('<h1>Agent not found</h1>', 404);
        }

        return $this->panel('merchant/agent_form', [
            'title' => (string) $agent['name'] . ' - Orin',
            'merchant' => $merchant,
            'agent' => $agent,
            'skills' => $agents->skillsFor((int) $merchant['id'], $id),
            'skillCatalog' => AgentService::SKILLS,
            'alwaysOn' => AgentService::ALWAYS_ON,
            'autonomyLevels' => AgentService::AUTONOMY,
            'tones' => AgentService::TONES,
            'stats' => $agents->stats((int) $merchant['id'], $id),
            'runs' => $agents->recentRuns((int) $merchant['id'], $id, 15),
            'knowledgeCount' => count(app('knowledge_service')->listFor((int) $merchant['id'], $id)),
            'notice' => app('session')->pullFlash('agent_notice'),
        ]);
    }

    public function update(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var AgentService $agents */
        $agents = app('agent_service');
        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        $agents->update((int) $merchant['id'], $id, [
            'name' => (string) $request->input('name', ''),
            'role_label' => (string) $request->input('role_label', 'Sales Assistant'),
            'autonomy' => (string) $request->input('autonomy', 'semi_auto'),
            'tone' => (string) $request->input('tone', 'friendly'),
            'language' => (string) $request->input('language', 'auto'),
            'system_instructions' => (string) $request->input('system_instructions', ''),
            'escalation_rules' => (string) $request->input('escalation_rules', ''),
            'after_hours_message' => (string) $request->input('after_hours_message', ''),
        ], (int) $request->attributes['user_id']);

        $selected = $request->input('skills');
        $agents->saveSkills((int) $merchant['id'], $id, is_array($selected) ? $selected : [], (int) $request->attributes['user_id']);

        if ($request->input('make_default') !== null) {
            $agents->makeDefault((int) $merchant['id'], $id, (int) $request->attributes['user_id']);
        }

        app('session')->flash('agent_notice', 'Saved.');

        return Response::redirect('/merchant/agents/' . $id . '/edit');
    }

    public function toggleStatus(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $status = (string) $request->input('status', 'paused');

        app('agent_service')->setStatus((int) $merchant['id'], $id, $status, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/agents');
    }
}
