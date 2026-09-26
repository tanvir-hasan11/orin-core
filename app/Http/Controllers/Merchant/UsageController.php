<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;
use Orin\Services\MerchantService;

final class UsageController extends BaseController
{
    public function index(Request $request): Response
    {
        $merchant = $this->requireMerchant($request);
        if ($merchant === []) {
            return Response::html('<h1>No merchant account</h1>', 404);
        }

        /** @var Database $db */
        $db = app('db');
        $mid = (int) $merchant['id'];

        /** @var MerchantService $service */
        $service = app('merchant_service');
        $summary = $service->usageSummary($mid);

        $byProvider = $db->select(
            'SELECT provider, SUM(prompt_tokens + completion_tokens) AS tokens, SUM(cost_usd) AS cost, COUNT(*) AS calls
             FROM usage_logs WHERE merchant_id = :mid GROUP BY provider ORDER BY tokens DESC',
            ['mid' => $mid]
        );

        $recent = $db->select(
            'SELECT provider, model, prompt_tokens, completion_tokens, cost_usd, latency_ms, status, created_at
             FROM usage_logs WHERE merchant_id = :mid ORDER BY id DESC LIMIT 25',
            ['mid' => $mid]
        );

        return $this->panel('merchant/usage', [
            'title' => 'Usage - Orin',
            'merchant' => $merchant,
            'summary' => $summary,
            'byProvider' => $byProvider,
            'recent' => $recent,
        ]);
    }
}
