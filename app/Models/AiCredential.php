<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A tenant's credentials for one AI provider. The raw secret is stored
 * encrypted; this model exposes encrypt/decrypt helpers so callers never touch
 * plaintext storage directly.
 */
final class AiCredential extends Model
{
    protected static string $table = 'ai_credentials';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'workspace_id', 'provider', 'label', 'credentials', 'meta',
        'is_active', 'is_default', 'last_used_at',
    ];

    protected static array $casts = [
        'meta'      => 'array',
        'is_active' => 'bool',
        'is_default' => 'bool',
    ];

    /**
     * Decrypt and return the stored credential payload (e.g. ['api_key'=>...]).
     */
    public function secrets(): array
    {
        $raw = $this->attributes['credentials'] ?? '';
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode(decrypt_value((string) $raw), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Encrypt a credential payload for storage.
     */
    public static function encryptSecrets(array $secrets): string
    {
        return encrypt_value(json_encode($secrets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    public static function forProvider(string $provider): ?self
    {
        $row = static::query()->where('provider', '=', $provider)->where('is_active', '=', 1)->first();

        return $row ? static::hydrate($row) : null;
    }

    /**
     * A masked hint of the stored key for display (never the full secret).
     */
    public function maskedKey(): string
    {
        $key = (string) ($this->secrets()['api_key'] ?? '');
        if ($key === '') {
            return '—';
        }

        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($key, 0, 4) . str_repeat('•', 8) . substr($key, -4);
    }
}
