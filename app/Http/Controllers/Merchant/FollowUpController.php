<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\FollowUpService;

/**
 * The follow-ups the agent asked for. cron/followups.php sends the due ones.
 */
final class FollowUpController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var FollowUpService $followups */
        $followups = app('followup_service');

        return $this->panel('merchant/followups', [
            'title' => 'Follow-ups - Orin',
            'merchant' => $merchant,
            'followups' => $followups->listFor((int) $merchant['id']),
            'pendingCount' => $followups->pendingCount((int) $merchant['id']),
        ]);
    }

    public function cancel(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        app('followup_service')->cancel((int) $merchant['id'], $id, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/followups');
    }
}
