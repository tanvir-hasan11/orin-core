<?php

declare(strict_types=1);

namespace Orin\Http\Controllers\Merchant;

use Orin\Core\Database;
use Orin\Core\Request;
use Orin\Core\Response;

final class BillingController extends BaseController
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

        $invoices = $db->select(
            'SELECT id, period, amount_usd, status, issued_at, paid_at
             FROM invoices WHERE merchant_id = :mid ORDER BY period DESC',
            ['mid' => $mid]
        );

        $plans = $db->select('SELECT id, name, code, price_usd, monthly_token_quota FROM plans WHERE is_active = 1 ORDER BY price_usd ASC');

        return $this->panel('merchant/billing', [
            'title' => 'Billing - Orin',
            'merchant' => $merchant,
            'invoices' => $invoices,
            'plans' => $plans,
        ]);
    }
}
