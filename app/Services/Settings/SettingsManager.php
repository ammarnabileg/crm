<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Contracts\Cache\CacheStore;
use App\Core\Database;
use App\Services\Tenancy\TenantManager;
use RuntimeException;

/**
 * Per-tenant settings store with config-backed defaults and a cache layer.
 *
 * Resolution order for a key: the workspace's stored value → the default in
 * config/settings.php → the caller's default. Writes upsert the tenant row and
 * bust the cache. This is the mechanism that keeps tenant-configurable values
 * (branding, locale, currency, hiring defaults, ...) out of hard-coded logic
 * (docs/47 EAS-9). Cache and DB are injected as dependencies (EAS-2).
 */
final class SettingsManager
{
    public function __construct(
        private readonly CacheStore $cache,
        private readonly Database $db,
        private readonly TenantManager $tenant,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $workspaceId = $this->requireTenant();
        $cacheKey = $this->cacheKey($workspaceId, $key);

        $value = $this->cache->remember(
            $cacheKey,
            (int) config('settings.cache_ttl', 300),
            function () use ($workspaceId, $key): array {
                $row = $this->db->table('settings')
                    ->where('workspace_id', '=', $workspaceId)
                    ->where('key', '=', $key)
                    ->first();

                // Wrap so a legitimately-null stored value is distinguishable
                // from "not stored" inside the cache.
                return ['stored' => $row !== null, 'value' => $row !== null ? $this->decode((string) $row['value']) : null];
            }
        );

        if ($value['stored']) {
            return $value['value'];
        }

        return $this->configDefault($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $workspaceId = $this->requireTenant();
        $encoded = $this->encode($value);
        $now = now();

        $exists = $this->db->table('settings')
            ->where('workspace_id', '=', $workspaceId)
            ->where('key', '=', $key)
            ->exists();

        if ($exists) {
            $this->db->table('settings')
                ->where('workspace_id', '=', $workspaceId)
                ->where('key', '=', $key)
                ->update(['value' => $encoded, 'updated_at' => $now]);
        } else {
            $this->db->table('settings')->insert([
                'workspace_id' => $workspaceId,
                'key'        => $key,
                'value'      => $encoded,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->cache->forget($this->cacheKey($workspaceId, $key));
    }

    public function has(string $key): bool
    {
        $workspaceId = $this->requireTenant();

        return $this->db->table('settings')
            ->where('workspace_id', '=', $workspaceId)
            ->where('key', '=', $key)
            ->exists();
    }

    public function forget(string $key): void
    {
        $workspaceId = $this->requireTenant();
        $this->db->table('settings')
            ->where('workspace_id', '=', $workspaceId)
            ->where('key', '=', $key)
            ->delete();
        $this->cache->forget($this->cacheKey($workspaceId, $key));
    }

    /**
     * All effective settings for the current tenant: config defaults overlaid
     * with stored values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $workspaceId = $this->requireTenant();
        $effective = (array) config('settings.defaults', []);

        foreach ($this->db->table('settings')->where('workspace_id', '=', $workspaceId)->get() as $row) {
            $effective[$row['key']] = $this->decode((string) $row['value']);
        }

        return $effective;
    }

    private function configDefault(string $key, mixed $default): mixed
    {
        $defaults = (array) config('settings.defaults', []);

        return $defaults[$key] ?? $default;
    }

    private function requireTenant(): int
    {
        $id = $this->tenant->id();
        if ($id === null) {
            throw new RuntimeException('Settings require an active tenant context.');
        }

        return $id;
    }

    private function cacheKey(int $workspaceId, string $key): string
    {
        return "settings:{$workspaceId}:{$key}";
    }

    private function encode(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function decode(string $value): mixed
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && (is_array($decoded)) ? $decoded : $value;
    }
}
