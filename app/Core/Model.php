<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Lightweight active-record base class.
 *
 * The crucial responsibility here is multi-tenant isolation: models flagged as
 * tenant-scoped automatically constrain every query to the current tenant and
 * stamp the tenant id on insert. A model can only escape that scope via the
 * explicit withoutTenantScope() escape hatch, which exists for system-level
 * (super-admin / cross-tenant) operations and is never reachable from normal
 * request flow.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    /**
     * When true, the model belongs to a tenant and every read/write is scoped
     * to the active company via the tenant column below.
     */
    protected static bool $tenantScoped = false;
    protected static string $tenantColumn = 'company_id';

    /** @var string[] Attributes that may be mass-assigned. */
    protected static array $fillable = [];

    /** @var string[] Attributes hidden from array/JSON output. */
    protected static array $hidden = [];

    /** @var array<string, string> Attribute => cast type (int, bool, float, array, datetime). */
    protected static array $casts = [];

    /**
     * When true, deletes set `deleted_at` instead of removing the row, and
     * default queries exclude trashed rows (docs/47 EAS-8).
     */
    protected static bool $softDeletes = false;
    protected static string $deletedAtColumn = 'deleted_at';

    /** When true, a public `uuid` is generated on insert (docs/47 EAS-8). */
    protected static bool $usesUuid = false;
    protected static string $uuidColumn = 'uuid';

    protected array $attributes = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function table(): string
    {
        return static::$table;
    }

    public static function keyName(): string
    {
        return static::$primaryKey;
    }

    protected static function db(): Database
    {
        return app('db');
    }

    /**
     * Tenant-scoped query builder (no soft-delete filter). Internal base used by
     * query()/withTrashed()/onlyTrashed().
     */
    protected static function baseQuery(): QueryBuilder
    {
        $builder = static::db()->table(static::$table);

        if (static::shouldApplyTenantScope()) {
            $tenantId = static::currentTenantId();
            if ($tenantId === null) {
                // Fail closed AND loud: never silently run an unscoped query on
                // a tenant table. Cross-tenant access must be explicit.
                throw new \RuntimeException(
                    static::class . ' is tenant-scoped but no active tenant is set. '
                    . 'Use ' . static::class . '::withoutTenantScope() for cross-tenant access.'
                );
            }
            $builder->where(static::$tenantColumn, '=', $tenantId);
        }

        return $builder;
    }

    /**
     * Default query: pre-scoped to the current tenant and excluding
     * soft-deleted rows (when the model uses soft deletes).
     */
    public static function query(): QueryBuilder
    {
        $builder = static::baseQuery();

        if (static::$softDeletes) {
            $builder->whereNull(static::$deletedAtColumn);
        }

        return $builder;
    }

    /**
     * Tenant-scoped query INCLUDING soft-deleted rows.
     */
    public static function withTrashed(): QueryBuilder
    {
        return static::baseQuery();
    }

    /**
     * Tenant-scoped query of ONLY soft-deleted rows.
     */
    public static function onlyTrashed(): QueryBuilder
    {
        return static::baseQuery()->whereNotNull(static::$deletedAtColumn);
    }

    /**
     * Query builder WITHOUT the tenant constraint (and without the soft-delete
     * filter). Reserved for system-level operations (super admin, cross-tenant
     * reporting, the installer).
     */
    public static function withoutTenantScope(): QueryBuilder
    {
        return static::db()->table(static::$table);
    }

    public static function findByUuid(string $uuid): ?static
    {
        $row = static::query()->where(static::$uuidColumn, '=', $uuid)->first();

        return $row ? static::hydrate($row) : null;
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()->where(static::$primaryKey, '=', $id)->first();

        return $row ? static::hydrate($row) : null;
    }

    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new HttpException(404, static::class . " [{$id}] not found.");
        }

        return $model;
    }

    public static function first(): ?static
    {
        $row = static::query()->first();

        return $row ? static::hydrate($row) : null;
    }

    /**
     * @return static[]
     */
    public static function all(): array
    {
        return array_map([static::class, 'hydrate'], static::query()->get());
    }

    /**
     * @return static[]
     */
    public static function where(string $column, mixed $operator = null, mixed $value = null): array
    {
        $builder = func_num_args() === 2
            ? static::query()->where($column, $operator)
            : static::query()->where($column, $operator, $value);

        return array_map([static::class, 'hydrate'], $builder->get());
    }

    public static function findBy(string $column, mixed $value): ?static
    {
        $row = static::query()->where($column, '=', $value)->first();

        return $row ? static::hydrate($row) : null;
    }

    public static function create(array $attributes): static
    {
        $attributes = static::filterFillable($attributes);

        if (static::shouldApplyTenantScope() && ! isset($attributes[static::$tenantColumn])) {
            $attributes[static::$tenantColumn] = static::currentTenantId();
        }

        if (static::$timestamps) {
            $attributes['created_at'] ??= now();
            $attributes['updated_at'] ??= now();
        }

        if (static::$usesUuid && empty($attributes[static::$uuidColumn])) {
            $attributes[static::$uuidColumn] = static::generateUuid();
        }

        $id = static::db()->table(static::$table)->insertGetId($attributes);
        $attributes[static::$primaryKey] = $id;

        $model = static::hydrate($attributes);
        $model->exists = true;

        return $model;
    }

    public function update(array $attributes): bool
    {
        $attributes = static::filterFillable($attributes);

        if (static::$timestamps) {
            $attributes['updated_at'] = now();
        }

        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return static::query()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->update($attributes) >= 0;
    }

    /**
     * Delete the model. Soft-delete models set deleted_at; others are removed.
     */
    public function delete(): bool
    {
        if (static::$softDeletes) {
            $affected = static::query()
                ->where(static::$primaryKey, '=', $this->getKey())
                ->update([static::$deletedAtColumn => now()]);
            $this->attributes[static::$deletedAtColumn] = now();

            return $affected > 0;
        }

        return static::query()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete() > 0;
    }

    /**
     * Permanently remove the row, even for soft-delete models.
     */
    public function forceDelete(): bool
    {
        return static::withTrashed()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->delete() > 0;
    }

    /**
     * Restore a soft-deleted model.
     */
    public function restore(): bool
    {
        if (! static::$softDeletes) {
            return false;
        }

        $affected = static::withTrashed()
            ->where(static::$primaryKey, '=', $this->getKey())
            ->update([static::$deletedAtColumn => null, 'updated_at' => now()]);
        $this->attributes[static::$deletedAtColumn] = null;

        return $affected > 0;
    }

    public function trashed(): bool
    {
        return static::$softDeletes && ! empty($this->attributes[static::$deletedAtColumn]);
    }

    public function save(): bool
    {
        if ($this->exists) {
            return $this->update($this->attributes);
        }

        $model = static::create($this->attributes);
        $this->attributes = $model->attributes;
        $this->exists = true;

        return true;
    }

    public function getKey(): mixed
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public static function hydrate(array $row): static
    {
        $model = new static($row);
        $model->exists = true;

        return $model;
    }

    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->castAttribute($key, $value);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, static::$hidden, true)) {
                continue;
            }
            $result[$key] = $this->castAttribute($key, $value);
        }

        return $result;
    }

    private function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || ! isset(static::$casts[$key])) {
            return $value;
        }

        return match (static::$casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json'   => is_array($value) ? $value : (json_decode((string) $value, true) ?? []),
            default            => $value,
        };
    }

    protected static function filterFillable(array $attributes): array
    {
        if (static::$fillable === []) {
            return $attributes;
        }

        $allowed = array_merge(static::$fillable, [
            static::$tenantColumn,
            static::$uuidColumn,
            static::$deletedAtColumn,
            'created_at',
            'updated_at',
        ]);

        return array_intersect_key($attributes, array_flip($allowed));
    }

    /**
     * Generate an RFC-4122 version 4 UUID without any external dependency.
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function shouldApplyTenantScope(): bool
    {
        if (! static::$tenantScoped) {
            return false;
        }

        $container = Container::getInstance();
        if (! $container->has('tenant')) {
            return false;
        }

        $tenant = $container->make('tenant');

        return $tenant->shouldScope();
    }

    private static function currentTenantId(): ?int
    {
        return Container::getInstance()->make('tenant')->id();
    }
}
