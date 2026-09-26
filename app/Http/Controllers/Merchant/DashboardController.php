<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\MerchantService;

final class DashboardController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1><p>Contact support.</p>', 404);
        }

        /** @var MerchantService $service */
        $service = app('merchant_service');
        $usage = $service->usageSummary((int) $merchant['id']);

        return $this->panel('merchant/dashboard', [
            'title' => 'Merchant Dashboard - Orin',
            'merchant' => $merchant,
            'usage' => $usage,
        ]);
    }
}
