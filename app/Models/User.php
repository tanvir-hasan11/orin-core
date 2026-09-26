<?php

declare(strict_types=1);

namespace Orin\Models;

use Orin\Core\Database;

final class User
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->first('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->first('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];

        return $this->db->insert('users', $data);
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $values['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('users', $values, ['id' => $id]);
    }
}
