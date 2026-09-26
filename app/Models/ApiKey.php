<?php

declare(strict_types=1);

namespace Orin\Models;

use Orin\Core\Database;

final class ApiKey
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forMerchant(int $merchantId): array
    {
        return $this->db->select(
            'SELECT id, label, key_prefix, last_used_at, revoked_at, created_at
             FROM api_keys
             WHERE merchant_id = :mid
             ORDER BY id DESC',
            ['mid' => $merchantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        return $this->db->insert('api_keys', $data);
    }

    public function revoke(int $merchantId, int $keyId): bool
    {
        return $this->db->execute(
            'UPDATE api_keys SET revoked_at = NOW(), updated_at = NOW()
             WHERE id = :id AND merchant_id = :mid AND revoked_at IS NULL',
            ['id' => $keyId, 'mid' => $merchantId]
        ) > 0;
    }

    public function countActive(int $merchantId): int
    {
        $row = $this->db->first(
            'SELECT COUNT(*) AS total FROM api_keys WHERE merchant_id = :mid AND revoked_at IS NULL',
            ['mid' => $merchantId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findByPrefix(string $prefix): ?array
    {
        return $this->db->first(
            'SELECT * FROM api_keys WHERE key_prefix = :prefix AND revoked_at IS NULL LIMIT 1',
            ['prefix' => $prefix]
        );
    }

    public function touch(int $keyId): void
    {
        $this->db->execute('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id', ['id' => $keyId]);
    }
}
