<?php

declare(strict_types=1);

namespace Orin\Core;

/**
 * AES-256-GCM encryption for values that must not sit in the database in
 * plaintext - channel access tokens, platform provider keys.
 *
 * Values written here are prefixed with "enc:". Anything without that prefix
 * is returned untouched, which keeps previously stored plaintext readable
 * while you migrate.
 */
final class Crypto
{
    private string $key;

    public function __construct(string $appKey)
    {
        $this->key = hash('sha256', $appKey === '' ? 'orin-development-key' : $appKey, true);
    }

    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            return $plain;
        }

        return 'enc:' . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        if (!str_starts_with($stored, 'enc:')) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }
}
