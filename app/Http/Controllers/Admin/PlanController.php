<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Admin;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class PlanController extends BaseController
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $plans = $db->select('SELECT * FROM plans ORDER BY price_usd ASC, id ASC');

        $counts = $db->select(
            'SELECT plan_id, COUNT(*) AS total FROM merchants WHERE plan_id IS NOT NULL GROUP BY plan_id'
        );

        $byPlan = [];
        foreach ($counts as $row) {
            $byPlan[(int) $row['plan_id']] = (int) $row['total'];
        }

        return $this->panel($request, 'admin/plans', [
            'title' => 'Plans - Orin Admin',
            'plans' => $plans,
            'merchantCounts' => $byPlan,
        ]);
    }

    public function store(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $name = trim((string) $request->input('name', ''));
        $code = strtolower(trim((string) $request->input('code', '')));

        if ($name === '' || $code === '' || preg_match('/^[a-z0-9_]+$/', $code) !== 1) {
            return Response::redirect('/admin/plans');
        }

        $now = date('Y-m-d H:i:s');
        $db->insert('plans', [
            'name' => $name,
            'code' => $code,
            'price_usd' => (float) $request->input('price_usd', 0),
            'monthly_token_quota' => (int) $request->input('monthly_token_quota', 100000),
            'max_api_keys' => (int) $request->input('max_api_keys', 3),
            'features' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->audit('plan.create', 'plan', $code, ['name' => $name]);

        return Response::redirect('/admin/plans');
    }

    public function update(Request $request): Response
    {
        /** @var Database $db */
        $db = app('db');

        $id = (int) ($request->attributes['route_params']['id'] ?? 0);

        $plan = $db->first('SELECT id FROM plans WHERE id = :id LIMIT 1', ['id' => $id]);
        if ($plan === null) {
            return Response::redirect('/admin/plans');
        }

        $values = [];

        if ($request->input('name') !== null) {
            $values['name'] = trim((string) $request->input('name'));
        }
        if ($request->input('price_usd') !== null) {
            $values['price_usd'] = (float) $request->input('price_usd');
        }
        if ($request->input('monthly_token_quota') !== null) {
            $values['monthly_token_quota'] = (int) $request->input('monthly_token_quota');
        }
        if ($request->input('max_api_keys') !== null) {
            $values['max_api_keys'] = (int) $request->input('max_api_keys');
        }
        if ($request->input('is_active') !== null) {
            $values['is_active'] = (int) ((bool) $request->input('is_active'));
        }

        if ($values !== []) {
            $db->update('plans', $values, ['id' => $id]);
            $this->audit('plan.update', 'plan', (string) $id, $values);
        }

        return Response::redirect('/admin/plans');
    }
}