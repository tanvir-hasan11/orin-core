<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class SettingsController extends BaseController
{
    /** Keys the super admin is allowed to edit from the panel. */
    private const ALLOWED = [
        'site_name',
        'support_email',
        'default_plan_code',
        'signup_open',
        'trial_days',
        'ai_fallback_message',
    ];

    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $rows = $db->select('SELECT `key`, `value` FROM settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['key']] = (string) ($row['value'] ?? '');
        }

        return $this->panel($request, 'admin/settings', [
            'title' => 'Settings - Orin Admin',
            'settings' => $settings,
            'allowed' => self::ALLOWED,
        ]);
    }

    public function update(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $now = date('Y-m-d H:i:s');
        $saved = [];

        foreach (self::ALLOWED as $key) {
            $value = $request->input($key);
            if ($value === null) {
                continue;
            }

            $db->execute(
                'INSERT INTO settings (`key`, `value`, updated_at)
                 VALUES (:k, :v, :t)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)',
                ['k' => $key, 'v' => (string) $value, 't' => $now]
            );

            $saved[$key] = (string) $value;
        }

        if ($saved !== []) {
            $this->audit('settings.update', 'settings', null, $saved);
        }

        return Response::redirect('/admin/settings');
    }
}