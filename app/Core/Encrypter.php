<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Authenticated symmetric encryption (AES-256-GCM).
 *
 * Used to protect sensitive tenant data at rest — most importantly the
 * per-tenant AI provider API keys, which must never be stored in plaintext.
 * The key is the application key generated at install time.
 */
final class Encrypter
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(string $key)
    {
        $this->key = $this->normalizeKey($key);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt the payload.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) < 28) {
            throw new RuntimeException('Invalid encryption payload.');
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt the payload (tampered or wrong key).');
        }

        return $plaintext;
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function normalizeKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        if (strlen($key) === 32) {
            return $key;
        }

        // Derive a 32-byte key from an arbitrary-length secret.
        return hash('sha256', $key, true);
    }
}
