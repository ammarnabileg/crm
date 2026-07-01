<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * The consistency boundary and entry point of a cluster of domain objects.
 *
 * An aggregate root is the only member of its cluster the outside world references directly; it
 * enforces the invariants that span the cluster and is the unit of persistence and concurrency.
 * It records {@see DomainEvent}s (via {@see RecordsDomainEvents}) as its state changes, which the
 * application layer pulls and dispatches after committing.
 */
abstract class AggregateRoot extends Entity
{
    use RecordsDomainEvents;
}
