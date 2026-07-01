<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginId;

/**
 * Recorded when a validated plugin is installed into the registry.
 *
 * Marks the transition of a plugin from discovered to installed: it has passed validation and
 * dependency resolution and now has a persistent registry record, though it is not yet enabled.
 * Emitted by {@see \Nizam\Platform\Plugin\RegisteredPlugin::install()}.
 */
final class PluginInstalled implements DomainEvent
{
    /**
     * @param PluginId          $pluginId   The installed plugin's identity.
     * @param string            $pluginName The installed plugin's manifest name.
     * @param string            $version    The installed version string.
     * @param TenantId|null     $tenantId   The owning tenant, or null for a global plugin.
     * @param DateTimeImmutable $occurredAt When the install happened.
     */
    public function __construct(
        private readonly PluginId $pluginId,
        private readonly string $pluginName,
        private readonly string $version,
        private readonly ?TenantId $tenantId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The installed plugin's identity.
     */
    public function pluginId(): PluginId
    {
        return $this->pluginId;
    }

    /**
     * The installed plugin's manifest name.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The installed version string.
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * The owning tenant, or null for a global plugin.
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * {@inheritDoc}
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * {@inheritDoc}
     */
    public function eventName(): string
    {
        return 'plugin.installed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginId->toString();
    }
}
