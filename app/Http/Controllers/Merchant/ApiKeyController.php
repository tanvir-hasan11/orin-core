<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\ApiKeyService;

final class ApiKeyController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var ApiKeyService $service */
        $service = app('api_key_service');

        $newKey = app('session')->pullFlash('new_api_key');

        return $this->panel('merchant/api_keys', [
            'title' => 'API Keys - Orin',
            'merchant' => $merchant,
            'keys' => $service->listFor((int) $merchant['id']),
            'newKey' => is_array($newKey) ? $newKey : null,
            'activeCount' => $service->activeCount((int) $merchant['id']),
        ]);
    }

    public function store(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $label = 'Untitled key';
        }

        /** @var ApiKeyService $service */
        $service = app('api_key_service');

        $maxKeys = (int) ($merchant['max_api_keys'] ?? 3);
        if ($service->activeCount((int) $merchant['id']) >= $maxKeys) {
            app('session')->flash('new_api_key', ['error' => 'You have reached the API key limit for your plan.']);

            return Response::redirect('/merchant/api-keys');
        }

        $created = $service->generate((int) $merchant['id'], $label, (int) $request->attributes['user_id']);

        app('session')->flash('new_api_key', [
            'plain' => $created['plain'],
            'label' => $label,
        ]);

        return Response::redirect('/merchant/api-keys');
    }

    public function revoke(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        $keyId = (int) ($request->attributes['route_params']['id'] ?? 0);

        /** @var ApiKeyService $service */
        $service = app('api_key_service');
        $service->revoke((int) $merchant['id'], $keyId, (int) $request->attributes['user_id']);

        return Response::redirect('/merchant/api-keys');
    }
}
