<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;

/**
 * An immutable, de-duplicated collection of {@see PluginPermission}s.
 *
 * A permission set models either the permissions a plugin *requires* (from its manifest) or the
 * permissions a tenant has *granted* it. The set is keyed by permission key, so adding two permissions
 * with the same key keeps only one. It answers membership ({@see self::has()}) and containment
 * ({@see self::grants()} — whether this set covers every permission of another), which is exactly what
 * the permission gate needs to decide whether a granted set satisfies a plugin's required set.
 */
final class PermissionSet implements ValueObject
{
    /**
     * @param array<string, PluginPermission> $permissions Permissions keyed by their canonical key.
     */
    private function __construct(
        private readonly array $permissions,
    ) {
    }

    /**
     * An empty permission set.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Build a set from a list of permissions, de-duplicating by key.
     *
     * @param list<PluginPermission> $permissions The permissions to include.
     */
    public static function of(array $permissions): self
    {
        $indexed = [];
        foreach ($permissions as $permission) {
            $indexed[$permission->key()] = $permission;
        }

        return new self($indexed);
    }

    /**
     * A copy of this set with an additional permission (idempotent by key).
     */
    public function with(PluginPermission $permission): self
    {
        $permissions = $this->permissions;
        $permissions[$permission->key()] = $permission;

        return new self($permissions);
    }

    /**
     * Whether this set contains a permission with the given key.
     */
    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }

    /**
     * Whether this set covers every permission required by another set.
     *
     * True when each key present in `$required` is also present here — i.e. this (granted) set is a
     * superset of the required set.
     */
    public function grants(self $required): bool
    {
        foreach ($required->permissions as $key => $_) {
            if (!isset($this->permissions[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The permissions in this set, as a list ordered by key.
     *
     * @return list<PluginPermission>
     */
    public function all(): array
    {
        $permissions = $this->permissions;
        ksort($permissions);

        return array_values($permissions);
    }

    /**
     * The canonical keys in this set, as a sorted list.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->permissions);
        sort($keys);

        return $keys;
    }

    /**
     * The number of distinct permissions in the set.
     */
    public function count(): int
    {
        return count($this->permissions);
    }

    /**
     * Whether the set contains no permissions.
     */
    public function isEmpty(): bool
    {
        return $this->permissions === [];
    }

    /**
     * Value equality by the exact set of keys.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->keys() === $other->keys();
    }
}
