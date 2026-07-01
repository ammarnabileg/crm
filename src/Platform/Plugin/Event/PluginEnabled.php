<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginId;

/**
 * Recorded when a plugin is enabled and begins actively participating.
 *
 * Marks the transition of an installed or disabled plugin to enabled. Emitted by
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin::enable()} once all required dependencies are enabled
 * and compatible.
 */
final class PluginEnabled implements DomainEvent
{
    /**
     * @param PluginId          $pluginId   The enabled plugin's identity.
     * @param string            $pluginName The enabled plugin's manifest name.
     * @param TenantId|null     $tenantId   The owning tenant, or null for a global plugin.
     * @param DateTimeImmutable $occurredAt When the enable happened.
     */
    public function __construct(
        private readonly PluginId $pluginId,
        private readonly string $pluginName,
        private readonly ?TenantId $tenantId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The enabled plugin's identity.
     */
    public function pluginId(): PluginId
    {
        return $this->pluginId;
    }

    /**
     * The enabled plugin's manifest name.
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
        return 'plugin.enabled';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginId->toString();
    }
}
