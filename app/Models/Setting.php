<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Per-tenant key/value settings store.
 */
final class Setting extends Model
{
    protected static string $table = 'settings';
    protected static bool $tenantScoped = true;

    protected static array $fillable = ['company_id', 'key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', '=', $key)->value('value');

        return $value === null ? $default : $value;
    }

    public static function put(string $key, string $value): void
    {
        $existing = static::query()->where('key', '=', $key)->first();

        if ($existing) {
            static::query()->where('key', '=', $key)->update(['value' => $value, 'updated_at' => now()]);
        } else {
            static::create(['key' => $key, 'value' => $value]);
        }
    }
}
