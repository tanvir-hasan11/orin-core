<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Models\Merchant;

final class MerchantService
{
    private Merchant $merchants;

    public function __construct(private Database $db)
    {
        $this->merchants = new Merchant($db);
    }

    /** @return array<string, mixed>|null */
    public function forUser(int $userId): ?array
    {
        $merchant = $this->merchants->findByOwner($userId);
        if ($merchant === null) {
            return null;
        }

        return $this->merchants->withPlan((int) $merchant['id']);
    }

    /** @return array<string, mixed> */
    public function usageSummary(int $merchantId): array
    {
        $period = date('Y-m');

        $monthly = $this->db->first(
            'SELECT tokens_used, cost_usd FROM usage_monthly WHERE merchant_id = :id AND period = :period LIMIT 1',
            ['id' => $merchantId, 'period' => $period]
        );

        $calls = $this->db->first(
            'SELECT COUNT(*) AS total FROM usage_logs WHERE merchant_id = :id',
            ['id' => $merchantId]
        );

        return [
            'period' => $period,
            'tokens_used' => (int) ($monthly['tokens_used'] ?? 0),
            'cost_usd' => (float) ($monthly['cost_usd'] ?? 0.0),
            'calls' => (int) ($calls['total'] ?? 0),
        ];
    }
}
