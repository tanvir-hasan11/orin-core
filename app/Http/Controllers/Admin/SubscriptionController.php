<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class SubscriptionController extends BaseController
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $subscriptions = $db->select(
            'SELECT s.id, s.status, s.starts_at, s.ends_at, s.created_at,
                    m.company_name, m.id AS merchant_id,
                    p.name AS plan_name, p.price_usd
             FROM subscriptions s
             JOIN merchants m ON m.id = s.merchant_id
             JOIN plans p ON p.id = s.plan_id
             ORDER BY s.id DESC
             LIMIT 200'
        );

        return $this->panel($request, 'admin/subscriptions', [
            'title' => 'Subscriptions - Orin Admin',
            'subscriptions' => $subscriptions,
        ]);
    }
}