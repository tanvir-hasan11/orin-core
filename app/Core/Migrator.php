<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * Runs .sql files from config/migrations in filename order.
 * Migrations are treated as idempotent (use IF NOT EXISTS in your SQL).
 */
final class Migrator
{
    public function __construct(
        private Database $db,
        private string $migrationPath,
    ) {
    }

    /** @return array<int, string> */
    public function run(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob($this->migrationPath . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            $this->db->pdo()->exec($sql);
            $applied[] = basename($file);
        }

        return $applied;
    }
}
