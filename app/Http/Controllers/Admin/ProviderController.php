<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Crypto;
use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

/**
 * Platform-level AI provider configuration.
 *
 * This is the super admin's job: which providers are enabled, their keys and
 * their priority. Merchants never see or touch this - they consume the engine.
 */
final class ProviderController extends BaseController
{
    /** Providers the engine knows how to speak to. */
    private const KNOWN = ['openai', 'gemini', 'openrouter'];

    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $rows = $db->select('SELECT * FROM platform_providers ORDER BY priority DESC, id ASC');

        $configured = [];
        foreach ($rows as $row) {
            $configured[(string) $row['provider']] = $row;
        }

        return $this->panel($request, 'admin/providers', [
            'title' => 'Providers - Orin Admin',
            'providers' => $rows,
            'configured' => $configured,
            'known' => self::KNOWN,
        ]);
    }

    public function save(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        /** @var Crypto $crypto */
        $crypto = app('crypto');

        $provider = strtolower(trim((string) $request->input('provider', '')));
        if (!in_array($provider, self::KNOWN, true)) {
            return Response::redirect('/admin/providers');
        }

        $enabled = (bool) $request->input('enabled', false);
        $model = trim((string) $request->input('default_model', ''));
        $priority = (int) $request->input('priority', 0);
        $apiKey = trim((string) $request->input('api_key', ''));

        $existing = $db->first(
            'SELECT id, api_key FROM platform_providers WHERE provider = :p LIMIT 1',
            ['p' => $provider]
        );

        $now = date('Y-m-d H:i:s');

        if ($existing === null) {
            $db->insert('platform_providers', [
                'provider' => $provider,
                'enabled' => $enabled ? 1 : 0,
                'api_key' => $apiKey === '' ? null : $crypto->encrypt($apiKey),
                'default_model' => $model,
                'priority' => $priority,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $values = [
                'enabled' => $enabled ? 1 : 0,
                'default_model' => $model,
                'priority' => $priority,
                'updated_at' => $now,
            ];

            // Only rewrite the key when a new one was actually typed in,
            // so an empty box does not wipe an existing credential.
            if ($apiKey !== '') {
                $values['api_key'] = $crypto->encrypt($apiKey);
            }

            $db->update('platform_providers', $values, ['id' => (int) $existing['id']]);
        }

        $this->audit('provider.save', 'platform_provider', $provider, [
            'enabled' => $enabled,
            'priority' => $priority,
            'model' => $model,
            'key_changed' => $apiKey !== '',
        ]);

        return Response::redirect('/admin/providers');
    }

    public function toggle(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        $row = $db->first('SELECT id, provider, enabled FROM platform_providers WHERE id = :id LIMIT 1', ['id' => $id]);
        if ($row === null) {
            return Response::redirect('/admin/providers');
        }

        $next = ((int) $row['enabled']) === 1 ? 0 : 1;
        $db->update('platform_providers', ['enabled' => $next], ['id' => $id]);

        $this->audit('provider.toggle', 'platform_provider', (string) $row['provider'], ['enabled' => $next]);

        return Response::redirect('/admin/providers');
    }
}