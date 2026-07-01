<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\TenantId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The runtime context handed to a plugin during lifecycle hooks, health checks and sandboxed calls.
 *
 * The context is the plugin's *only* window onto the platform: it carries the tenant the operation
 * runs for (or null for a global/platform-scoped plugin), the {@see PermissionSet} the tenant has
 * granted the plugin, the resolved configuration for the plugin, and a PSR-3 logger the plugin may
 * write to. By passing capabilities in explicitly — rather than letting plugins reach into global
 * state — the context is the first half of the isolation model (the permission gate is the second):
 * a plugin can only ever see the tenant, permissions and config it was handed. The context is
 * immutable; derive a narrowed copy with {@see self::withConfig()} when needed.
 */
final class PluginContext
{
    /**
     * @param TenantId|null        $tenantId          The tenant the operation runs for, or null when global.
     * @param PermissionSet        $grantedPermissions The permissions the tenant granted the plugin.
     * @param array<string, mixed> $config            The resolved configuration for the plugin.
     * @param LoggerInterface      $logger            The logger the plugin may write to.
     */
    public function __construct(
        private readonly ?TenantId $tenantId,
        private readonly PermissionSet $grantedPermissions,
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Build a global (tenant-less) context with no granted permissions and a null logger.
     *
     * @param array<string, mixed> $config The resolved configuration for the plugin.
     */
    public static function global(array $config = []): self
    {
        return new self(null, PermissionSet::empty(), $config, new NullLogger());
    }

    /**
     * Build a tenant-scoped context.
     *
     * @param array<string, mixed> $config The resolved configuration for the plugin.
     */
    public static function forTenant(
        TenantId $tenantId,
        PermissionSet $grantedPermissions,
        array $config = [],
        ?LoggerInterface $logger = null,
    ): self {
        return new self($tenantId, $grantedPermissions, $config, $logger ?? new NullLogger());
    }

    /**
     * The tenant the operation runs for, or null when the plugin is global.
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * Whether this context is scoped to a specific tenant.
     */
    public function isTenantScoped(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * The permissions the tenant granted the plugin.
     */
    public function grantedPermissions(): PermissionSet
    {
        return $this->grantedPermissions;
    }

    /**
     * The resolved configuration for the plugin.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * A single configuration value, or the given default when absent.
     */
    public function configValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * The logger the plugin may write to.
     */
    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * A copy of this context with replaced configuration.
     *
     * @param array<string, mixed> $config The configuration to substitute.
     */
    public function withConfig(array $config): self
    {
        return new self($this->tenantId, $this->grantedPermissions, $config, $this->logger);
    }
}
