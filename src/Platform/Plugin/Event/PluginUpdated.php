<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginId;

/**
 * Recorded when a plugin is updated from one version to a strictly newer one.
 *
 * Carries both the previous and the new version so consumers can react to the change (for example,
 * re-index capabilities or run a migration). Emitted by
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin::update()}.
 */
final class PluginUpdated implements DomainEvent
{
    /**
     * @param PluginId          $pluginId       The updated plugin's identity.
     * @param string            $pluginName     The updated plugin's manifest name.
     * @param string            $previousVersion The version the plugin was updated from.
     * @param string            $newVersion     The version the plugin was updated to.
     * @param TenantId|null     $tenantId       The owning tenant, or null for a global plugin.
     * @param DateTimeImmutable $occurredAt     When the update happened.
     */
    public function __construct(
        private readonly PluginId $pluginId,
        private readonly string $pluginName,
        private readonly string $previousVersion,
        private readonly string $newVersion,
        private readonly ?TenantId $tenantId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The updated plugin's identity.
     */
    public function pluginId(): PluginId
    {
        return $this->pluginId;
    }

    /**
     * The updated plugin's manifest name.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The version the plugin was updated from.
     */
    public function previousVersion(): string
    {
        return $this->previousVersion;
    }

    /**
     * The version the plugin was updated to.
     */
    public function newVersion(): string
    {
        return $this->newVersion;
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
        return 'plugin.updated';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginId->toString();
    }
}
