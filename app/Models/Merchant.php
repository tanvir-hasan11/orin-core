<?php

declare(strict_types=1);

namespace Orin\Models;

use Orin\Core\Database;

final class Merchant
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM merchants WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByOwner(int $userId): ?array
    {
        return $this->db->first('SELECT * FROM merchants WHERE owner_user_id = :uid LIMIT 1', ['uid' => $userId]);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM merchants WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        return $this->db->insert('merchants', $data);
    }

    /** @return array<string, mixed>|null */
    public function withPlan(int $merchantId): ?array
    {
        return $this->db->first(
            'SELECT m.*, p.name AS plan_name, p.code AS plan_code, p.monthly_token_quota, p.price_usd
             FROM merchants m
             LEFT JOIN plans p ON p.id = m.plan_id
             WHERE m.id = :id LIMIT 1',
            ['id' => $merchantId]
        );
    }

    public static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'merchant' : $value;
    }
}
