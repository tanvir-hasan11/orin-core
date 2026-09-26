<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class ProviderController extends BaseController
{
    private const PROVIDERS = ['openai', 'gemini', 'openrouter'];

    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var Database $db */
        $db = app('db');

        $rows = $db->select(
            'SELECT provider, enabled, default_model, has_custom_key
             FROM merchant_providers WHERE merchant_id = :mid',
            ['mid' => (int) $merchant['id']]
        );

        $settings = [];
        foreach (self::PROVIDERS as $p) {
            $settings[$p] = ['enabled' => 1, 'default_model' => '', 'has_custom_key' => 0];
        }
        foreach ($rows as $row) {
            $settings[(string) $row['provider']] = $row;
        }

        return $this->panel('merchant/providers', [
            'title' => 'Providers - Orin',
            'merchant' => $merchant,
            'providers' => self::PROVIDERS,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var Database $db */
        $db = app('db');
        $mid = (int) $merchant['id'];

        foreach (self::PROVIDERS as $provider) {
            $enabled = $request->input($provider . '_enabled') ? 1 : 0;
            $model = trim((string) $request->input($provider . '_model', ''));

            $existing = $db->first(
                'SELECT id FROM merchant_providers WHERE merchant_id = :mid AND provider = :p LIMIT 1',
                ['mid' => $mid, 'p' => $provider]
            );

            if ($existing === null) {
                $db->insert('merchant_providers', [
                    'merchant_id' => $mid,
                    'provider' => $provider,
                    'enabled' => $enabled,
                    'default_model' => $model,
                    'has_custom_key' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->update('merchant_providers', [
                    'enabled' => $enabled,
                    'default_model' => $model,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $existing['id']]);
            }
        }

        app('audit')->log('provider.update', 'merchant', (string) $mid, (int) $request->attributes['user_id'], 'merchant');

        return Response::redirect('/merchant/providers');
    }
}
