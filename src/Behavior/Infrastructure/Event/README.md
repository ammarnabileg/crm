# Behavior\Infrastructure\Event

**Purpose.** The adapter that carries the Behavior module's domain events out to the platform event
pipeline, so the domain and application layers stay free of any dispatch machinery.

**Responsibilities.**
- `DispatchingBehaviorEventPublisher` — implements the `BehaviorEventPublisher` port by dispatching
  each pulled `DomainEvent`, in recorded order, through the platform PSR-14 `EventDispatcher`.

**Dependencies.** The Behavior `Domain\Port\BehaviorEventPublisher` and `Nizam\Kernel\Domain\DomainEvent`;
`Nizam\Platform\Event\EventDispatcher`.

**Public interfaces.** Implements `BehaviorEventPublisher`. The `BehaviorServiceProvider` binds the
port to this adapter; application handlers pull events from aggregates and hand them to the port.
