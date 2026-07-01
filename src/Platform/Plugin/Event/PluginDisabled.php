<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginId;

/**
 * Recorded when a plugin is disabled and stops participating.
 *
 * Marks the transition of an enabled plugin to disabled, without uninstalling it. Emitted by
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin::disable()}.
 */
final class PluginDisabled implements DomainEvent
{
    /**
     * @param PluginId          $pluginId   The disabled plugin's identity.
     * @param string            $pluginName The disabled plugin's manifest name.
     * @param TenantId|null     $tenantId   The owning tenant, or null for a global plugin.
     * @param DateTimeImmutable $occurredAt When the disable happened.
     */
    public function __construct(
        private readonly PluginId $pluginId,
        private readonly string $pluginName,
        private readonly ?TenantId $tenantId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The disabled plugin's identity.
     */
    public function pluginId(): PluginId
    {
        return $this->pluginId;
    }

    /**
     * The disabled plugin's manifest name.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
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
        return 'plugin.disabled';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginId->toString();
    }
}
