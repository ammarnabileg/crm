<?php

declare(strict_types=1);

namespace HaHireAI\Shared;

use RuntimeException;

/**
 * Authenticated symmetric encryption (AES-256-GCM) for secrets at rest —
 * e.g. workspace AI provider keys, which are never stored or shown in plaintext
 * (docs/SECURITY_GUIDE.md, docs/AI_SECURITY.md).
 */
final class Encrypter
{
    private string $key;

    public function __construct(string $appKey)
    {
        $this->key = str_starts_with($appKey, 'base64:')
            ? (base64_decode(substr($appKey, 7), true) ?: '')
            : $appKey;

        if (strlen($this->key) < 32) {
            // Derive a stable 32-byte key from a short/empty app key (dev fallback).
            $this->key = hash('sha256', 'hahireai:' . $appKey, true);
        } else {
            $this->key = substr($this->key, 0, 32);
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Invalid ciphertext.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Decryption failed (tampered or wrong key).');
        }

        return $plain;
    }

    /** A non-reversible last-4 hint for display (never the secret itself). */
    public function hint(string $plaintext): string
    {
        return strlen($plaintext) <= 4 ? '••••' : '••••' . substr($plaintext, -4);
    }
}
