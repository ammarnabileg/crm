<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A subscription plan. Plans are data — the catalogue grows by inserting rows,
 * not by changing code. Features/limits are JSON so plans can gate behaviour.
 */
final class Plan extends Model
{
    protected static string $table = 'plans';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'interval',
        'trial_days', 'features', 'limits', 'is_active', 'is_public', 'sort_order',
    ];

    protected static array $casts = [
        'features'   => 'array',
        'limits'     => 'array',
        'is_active'  => 'bool',
        'is_public'  => 'bool',
        'price'      => 'float',
        'trial_days' => 'int',
        'sort_order' => 'int',
    ];

    /**
     * @return self[] Active, public plans for the pricing/upgrade screens.
     */
    public static function active(): array
    {
        return array_map(
            [self::class, 'hydrate'],
            static::query()->where('is_active', '=', 1)->where('is_public', '=', 1)->orderBy('sort_order')->get()
        );
    }

    public function feature(string $key, mixed $default = null): mixed
    {
        $features = $this->getAttribute('features') ?? [];

        return $features[$key] ?? $default;
    }

    public function limit(string $key, mixed $default = null): mixed
    {
        $limits = $this->getAttribute('limits') ?? [];

        return $limits[$key] ?? $default;
    }

    public function formattedPrice(): string
    {
        return number_format((float) $this->attributes['price'], 2) . ' ' . ($this->attributes['currency'] ?? 'SAR');
    }
}
