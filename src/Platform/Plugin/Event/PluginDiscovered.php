<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Event;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Plugin\PluginKind;

/**
 * Recorded when a plugin is discovered from a source but not yet installed.
 *
 * Marks the first time the platform has seen a particular plugin name and version, together with the
 * source locator it was found at. Emitted by the discovery service; useful for auditing where plugins
 * originate.
 */
final class PluginDiscovered implements DomainEvent
{
    /**
     * @param string            $pluginName The discovered plugin's manifest name.
     * @param string            $version    The discovered version string.
     * @param PluginKind        $kind       The plugin's kind.
     * @param string            $source     The source locator the plugin was discovered at.
     * @param DateTimeImmutable $occurredAt When the discovery happened.
     */
    public function __construct(
        private readonly string $pluginName,
        private readonly string $version,
        private readonly PluginKind $kind,
        private readonly string $source,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The discovered plugin's manifest name.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The discovered version string.
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * The plugin's kind.
     */
    public function kind(): PluginKind
    {
        return $this->kind;
    }

    /**
     * The source locator the plugin was discovered at.
     */
    public function source(): string
    {
        return $this->source;
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
        return 'plugin.discovered';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->pluginName;
    }
}
