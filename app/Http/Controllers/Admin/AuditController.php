<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class AuditController extends BaseController
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $action = trim((string) $request->query['action'] ?? '');

        if ($action !== '') {
            $logs = $db->select(
                'SELECT a.*, u.email AS actor_email
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.actor_user_id
                 WHERE a.action LIKE :action
                 ORDER BY a.id DESC
                 LIMIT 200',
                ['action' => $action . '%']
            );
        } else {
            $logs = $db->select(
                'SELECT a.*, u.email AS actor_email
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.actor_user_id
                 ORDER BY a.id DESC
                 LIMIT 200'
            );
        }

        return $this->panel($request, 'admin/audit', [
            'title' => 'Audit Log - Orin Admin',
            'logs' => $logs,
            'action' => $action,
        ]);
    }
}