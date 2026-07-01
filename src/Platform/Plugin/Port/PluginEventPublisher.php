<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Port;

use Nizam\Kernel\Domain\DomainEvent;

/**
 * The port through which pulled plugin domain events leave the module for dispatch.
 *
 * {@see \Nizam\Platform\Plugin\RegisteredPlugin} aggregates buffer {@see DomainEvent}s as they change;
 * the application layer pulls those events after the unit of work commits and hands them to this port.
 * A concrete adapter in Infrastructure bridges to the platform event dispatcher, keeping the domain
 * free of dispatch machinery.
 */
interface PluginEventPublisher
{
    /**
     * Publish a batch of domain events, in order.
     *
     * @param list<DomainEvent> $events The events to publish.
     */
    public function publish(array $events): void;
}
