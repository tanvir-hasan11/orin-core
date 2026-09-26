<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\LeadService;
use Orin\Services\VerticalRegistry;

final class LeadController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var LeadService $leads */
        $leads = app('lead_service');
        /** @var VerticalRegistry $verticals */
        $verticals = app('vertical_registry');

        $profile = app('conversation_service')->profile((int) $merchant['id']);
        $type = (string) ($profile['business_type'] ?? 'generic');
        $stage = (string) ($request->query['stage'] ?? '');

        return $this->panel('merchant/leads', [
            'title' => 'Leads - Orin',
            'merchant' => $merchant,
            'leads' => $leads->listFor((int) $merchant['id'], $stage !== '' ? $stage : null),
            'counts' => $leads->countsByStage((int) $merchant['id']),
            'stages' => $verticals->stages($type),
            'activeStage' => $stage,
        ]);
    }

    public function updateStage(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $stage = (string) $request->input('stage', '');

        /** @var LeadService $leads */
        $leads = app('lead_service');
        $leads->moveStage((int) $merchant['id'], $id, $stage, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/leads');
    }
}
