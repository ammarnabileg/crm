<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PluginId;

/**
 * Recorded when a plugin fails — a lifecycle hook threw, a health check errored, or a sandboxed call
 * crashed.
 *
 * Carries the failure reason so operators can diagnose the fault. This is the event the fault-catching
 * sandbox emits when it converts a thrown {@see \Throwable} into a failure, ensuring a plugin crash is
 * always observable without ever propagating into the Core. Emitted by
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin::markFailed()}.
 */
final class PluginFailed implements DomainEvent
{
    /**
     * @param PluginId          $pluginId   The failed plugin's identity.
     * @param string            $pluginName The failed plugin's manifest name.
     * @param string            $reason     The human-readable failure reason.
     * @param TenantId|null     $tenantId   The owning tenant, or null for a global plugin.
     * @param DateTimeImmutable $occurredAt When the failure occurred.
     */
    public function __construct(
        private readonly PluginId $pluginId,
        private readonly string $pluginName,
        private readonly string $reason,
        private readonly ?TenantId $tenantId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The failed plugin's identity.
     */
    public function pluginId(): PluginId
    {
        return $this->pluginId;
    }

    /**
     * The failed plugin's manifest name.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The human-readable failure reason.
     */
    public function reason(): string
    {
        return $this->reason;
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
        return 'plugin.failed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginId->toString();
    }
}
