<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class MerchantController extends BaseController
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $merchants = $db->select(
            'SELECT m.id, m.company_name, m.slug, m.status, m.created_at, m.plan_id,
                    u.email AS owner_email, u.name AS owner_name,
                    p.name AS plan_name
             FROM merchants m
             JOIN users u ON u.id = m.owner_user_id
             LEFT JOIN plans p ON p.id = m.plan_id
             ORDER BY m.id DESC
             LIMIT 200'
        );

        return $this->panel($request, 'admin/merchants', [
            'title' => 'Merchants - Orin Admin',
            'merchants' => $merchants,
        ]);
    }

    public function show(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        $merchant = $db->first(
            'SELECT m.*, u.email AS owner_email, u.name AS owner_name, p.name AS plan_name
             FROM merchants m
             JOIN users u ON u.id = m.owner_user_id
             LEFT JOIN plans p ON p.id = m.plan_id
             WHERE m.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if ($merchant === null) {
            return Response::notFound();
        }

        $subscriptions = $db->select(
            'SELECT s.*, p.name AS plan_name
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.merchant_id = :id
             ORDER BY s.id DESC
             LIMIT 50',
            ['id' => $id]
        );

        $plans = $db->select('SELECT id, name, code, price_usd FROM plans WHERE is_active = 1 ORDER BY price_usd ASC');

        return $this->panel($request, 'admin/merchant_show', [
            'title' => 'Merchant - Orin Admin',
            'merchant' => $merchant,
            'subscriptions' => $subscriptions,
            'plans' => $plans,
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $status = (string) $request->input('status', '');

        if (!in_array($status, ['trial', 'active', 'suspended'], true)) {
            return Response::redirect('/admin/merchants/' . $id);
        }

        $db->update('merchants', ['status' => $status], ['id' => $id]);
        $this->audit('merchant.status', 'merchant', (string) $id, ['status' => $status]);

        return Response::redirect('/admin/merchants/' . $id);
    }

    public function assignPlan(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);
        $planId = (int) $request->input('plan_id', 0);

        $plan = $db->first('SELECT id FROM plans WHERE id = :id LIMIT 1', ['id' => $planId]);
        if ($plan === null) {
            return Response::redirect('/admin/merchants/' . $id);
        }

        $db->update('merchants', ['plan_id' => $planId], ['id' => $id]);

        $db->execute(
            'UPDATE subscriptions SET status = :expired WHERE merchant_id = :id AND status = :active',
            ['expired' => 'expired', 'id' => $id, 'active' => 'active']
        );

        $now = date('Y-m-d H:i:s');
        $db->insert('subscriptions', [
            'merchant_id' => $id,
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('merchant.plan', 'merchant', (string) $id, ['plan_id' => $planId]);

        return Response::redirect('/admin/merchants/' . $id);
    }
}