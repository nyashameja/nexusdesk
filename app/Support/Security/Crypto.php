<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Authenticated symmetric encryption (AES-256-GCM) for secrets stored in the
 * database — e.g. Zoho OAuth refresh tokens. Keyed by the app's APP_KEY.
 */
final class Crypto
{
    private static function key(): string
    {
        $key = (string) config('app.key', '');
        // APP_KEY is base64; fall back to a hash so encryption never hard-fails,
        // though a proper key should always be set by the installer.
        $decoded = base64_decode($key, true);
        if ($decoded !== false && strlen($decoded) >= 32) {
            return substr($decoded, 0, 32);
        }
        return substr(hash('sha256', $key ?: 'nexusdesk', true), 0, 32);
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return '';
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}
