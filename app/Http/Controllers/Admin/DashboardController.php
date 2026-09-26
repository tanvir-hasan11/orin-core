<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class DashboardController
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $merchants = $db->first('SELECT COUNT(*) AS total FROM merchants');
        $users = $db->first('SELECT COUNT(*) AS total FROM users');
        $calls = $db->first('SELECT COUNT(*) AS total FROM usage_logs');
        $tokens = $db->first('SELECT COALESCE(SUM(tokens_used),0) AS total FROM usage_monthly');
        $recent = $db->select('SELECT id, company_name, slug, status, created_at FROM merchants ORDER BY id DESC LIMIT 10');

        return Response::html(view('admin/dashboard', [
            'title' => 'Admin Dashboard - Orin',
            'stats' => [
                'merchants' => (int) ($merchants['total'] ?? 0),
                'users' => (int) ($users['total'] ?? 0),
                'calls' => (int) ($calls['total'] ?? 0),
                'tokens' => (int) ($tokens['total'] ?? 0),
            ],
            'recent' => $recent,
        ]));
    }
}
