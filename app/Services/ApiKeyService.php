<?php

declare(strict_types=1);

namespace Orin\Services;

use Orin\Core\Database;
use Orin\Models\ApiKey;

final class ApiKeyService
{
    private ApiKey $keys;

    public function __construct(
        Database $db,
        private AuditService $audit,
    ) {
        $this->keys = new ApiKey($db);
    }

    /**
     * Generate a new key. Returns the plaintext key ONCE plus the record id.
     *
     * @return array{id:int, plain:string, prefix:string}
     */
    public function generate(int $merchantId, string $label, int $actorUserId): array
    {
        $prefix = bin2hex(random_bytes(6));
        $secret = bin2hex(random_bytes(24));
        $plain = 'orin_' . $prefix . '_' . $secret;

        $id = $this->keys->create([
            'merchant_id' => $merchantId,
            'label' => $label,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
        ]);

        $this->audit->log('apikey.create', 'api_key', (string) $id, $actorUserId, 'merchant', [
            'merchant_id' => $merchantId,
            'label' => $label,
        ]);

        return ['id' => $id, 'plain' => $plain, 'prefix' => $prefix];
    }

    public function revoke(int $merchantId, int $keyId, int $actorUserId): bool
    {
        $ok = $this->keys->revoke($merchantId, $keyId);

        if ($ok) {
            $this->audit->log('apikey.revoke', 'api_key', (string) $keyId, $actorUserId, 'merchant', [
                'merchant_id' => $merchantId,
            ]);
        }

        return $ok;
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $merchantId): array
    {
        return $this->keys->forMerchant($merchantId);
    }

    public function activeCount(int $merchantId): int
    {
        return $this->keys->countActive($merchantId);
    }
}
