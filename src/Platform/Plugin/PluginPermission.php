<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\Exception\PluginManifestException;

/**
 * A single capability a plugin declares it needs, and that a tenant may grant it.
 *
 * A permission is a dotted, lowercase capability key (for example `tools.invoke`, `crm.contacts.read`)
 * plus a human-readable description shown to the operator who decides whether to grant it. Permissions
 * are the unit of the platform's isolation model: a plugin declares the permissions it requires in its
 * manifest, the tenant grants a subset, and the {@see \Nizam\Platform\Plugin\Service\PluginPermissionGate}
 * refuses any action whose permission was not granted. The key format is validated on construction so
 * that grants and checks always compare canonical strings.
 */
final class PluginPermission implements ValueObject
{
    /**
     * The permitted key shape: dotted segments of lowercase letters, digits and underscores.
     */
    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/';

    /**
     * @param string $key         The canonical, dotted capability key (e.g. `tools.invoke`).
     * @param string $description A human-readable description of what the permission allows.
     *
     * @throws PluginManifestException When the key is empty or not a canonical dotted key.
     */
    public function __construct(
        private readonly string $key,
        private readonly string $description = '',
    ) {
        $normalized = trim($key);
        if ($normalized === '' || preg_match(self::KEY_PATTERN, $normalized) !== 1) {
            throw PluginManifestException::invalidPermissionKey($key);
        }
    }

    /**
     * The canonical capability key.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * The human-readable description of the permission.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Value equality by capability key (the description is presentational only).
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->key === $other->key;
    }

    /**
     * The canonical capability key.
     */
    public function __toString(): string
    {
        return $this->key;
    }
}
