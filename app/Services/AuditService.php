<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;

final class AuditService
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string, mixed> $meta */
    public function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        array $meta = [],
    ): void {
        $this->db->insert('audit_logs', [
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
