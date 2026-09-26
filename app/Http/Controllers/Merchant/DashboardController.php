<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\MerchantService;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->attributes['user_id'];

        /** @var MerchantService $service */
        $service = app('merchant_service');
        $merchant = $service->forUser($userId);

        if ($merchant === null) {
            return Response::html('<h1>No merchant account</h1><p>Contact support.</p>', 404);
        }

        $usage = $service->usageSummary((int) $merchant['id']);

        return Response::html(view('merchant/dashboard', [
            'title' => 'Merchant Dashboard - Orin',
            'merchant' => $merchant,
            'usage' => $usage,
        ]));
    }
}
