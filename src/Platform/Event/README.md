# Platform\Event

**Purpose.** The PSR-14 event pipeline: register listeners and dispatch events through the platform.

**Responsibilities.**
- `ListenerProvider` — register listeners per event class with priority; matches subtypes; returns listeners highest-priority-first (stable).
- `EventDispatcher` — dispatch an event to its listeners; honours `StoppableEventInterface`.
- `Subscriber` — interface for classes that declare their event handlers in one place.

**Dependencies.** `psr/event-dispatcher`.

**Public interfaces.** `EventDispatcher` (`EventDispatcherInterface`), `ListenerProvider` (`ListenerProviderInterface`), `Subscriber`.
