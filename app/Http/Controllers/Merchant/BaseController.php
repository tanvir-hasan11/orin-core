<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\MerchantService;

/**
 * Shared helpers for merchant controllers.
 */
abstract class BaseController
{
    /** @return array<string, mixed> */
    protected function requireMerchant(Request $request): array
    {
        /** @var MerchantService $service */
        $service = app('merchant_service');
        $merchant = $service->forUser((int) $request->attributes['user_id']);

        if ($merchant === null) {
            return [];
        }

        return $merchant;
    }

    /** @param array<string, mixed> $data */
    protected function panel(string $template, array $data): Response
    {
        $data['sidebar'] = merchant_sidebar();

        return Response::html(view($template, $data, 'layouts/panel'));
    }
}
