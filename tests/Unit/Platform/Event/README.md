# tests/Unit/Platform/Event

**Purpose.** Unit tests for the PSR-14 event system (`Nizam\Platform\Event`).

**Responsibilities.**
- `EventDispatcherTest` — listener priority ordering, stable order for equal priority, stoppable-event short-circuiting, and subtype/interface listener matching.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Platform\Event\EventDispatcher` and `ListenerProvider`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
