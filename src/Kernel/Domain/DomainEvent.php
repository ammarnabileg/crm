<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

use DateTimeImmutable;

/**
 * Something meaningful that happened in the domain, expressed in the ubiquitous language.
 *
 * Aggregates record domain events (via {@see RecordsDomainEvents}) as they change state; the
 * application layer pulls and dispatches them after the transaction commits. Every event carries
 * when it occurred, a stable name for routing/serialization, and the id of the aggregate that
 * produced it.
 */
interface DomainEvent
{
    /**
     * The instant the event occurred.
     */
    public function occurredAt(): DateTimeImmutable;

    /**
     * A stable, human-readable name for the event (e.g. "identity.user_registered").
     *
     * Used for routing, logging and serialization; must not change once published.
     */
    public function eventName(): string;

    /**
     * The string id of the aggregate that raised this event.
     */
    public function aggregateId(): string;
}
