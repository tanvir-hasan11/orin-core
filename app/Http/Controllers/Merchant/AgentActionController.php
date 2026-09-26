<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

/**
 * Orders, appointments, catalogue requests and delivery-area notes the agent
 * recorded. This is the merchant's queue of things the agent did on its own.
 */
final class AgentActionController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var Database $db */
        $db = app('db');
        $kind = (string) ($request->query['kind'] ?? '');

        $sql = 'SELECT a.*, c.name AS contact_name, c.phone AS contact_phone, ag.name AS agent_name
                FROM agent_actions a
                LEFT JOIN contacts c ON c.id = a.contact_id
                LEFT JOIN agents ag ON ag.id = a.agent_id
                WHERE a.merchant_id = :m';

        $params = ['m' => (int) $merchant['id']];

        if ($kind !== '') {
            $sql .= ' AND a.kind = :k';
            $params['k'] = $kind;
        }

        $sql .= ' ORDER BY FIELD(a.status, :pending, :confirmed, :done, :cancelled), a.id DESC LIMIT 200';
        $params['pending'] = 'pending';
        $params['confirmed'] = 'confirmed';
        $params['done'] = 'done';
        $params['cancelled'] = 'cancelled';

        return $this->panel('merchant/actions', [
            'title' => 'Agent Actions - Orin',
            'merchant' => $merchant,
            'actions' => $db->select($sql, $params),
            'activeKind' => $kind,
        ]);
    }

    public function setStatus(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $status = (string) $request->input('status', 'done');

        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'done'], true)) {
            return Response::redirect('/merchant/actions');
        }

        app('db')->execute(
            'UPDATE agent_actions SET status = :s, updated_at = :u WHERE id = :id AND merchant_id = :m',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'm' => (int) $merchant['id']]
        );

        app('audit')->log('agent_action.status', 'agent_action', (string) $id, (int) $request->attributes['user_id'], 'merchant', ['status' => $status]);

        return Response::redirect('/merchant/actions');
    }
}
