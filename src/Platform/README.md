# Platform

**Purpose.** `Nizam\Platform\*` — the self-built framework: the platform's replaceable, cross-cutting infrastructure. Per [ADR-0015](../../docs/16-ADR.md#adr-0015), the platform *is* our framework; every component sits behind a PSR contract we own so it can be swapped without touching callers.

**Responsibilities (foundation spine delivered so far).**
- `Support/` — pure, dependency-free utilities (`Result`, `Uuid` v7, `SystemClock`, `Assert`, `Str`, `Json`).
- `Exception/` — the typed exception hierarchy (`PlatformException` base).
- `Container/` — the PSR-11 DI container with autowiring.
- `Config/` — dot-access configuration + environment access.
- `Event/` — the PSR-14 event dispatcher and listener provider.
- `Logging/` — PSR-3 logging (`LogManager`, `Logger`, handlers).
- `Bootstrap/` — the composition root (`Application`, `CoreServiceProvider`, `Environment`).

The remaining Phase-2 infrastructure (HTTP, routing, database, cache, queue, scheduler, validation, storage, localization, notifications, monitoring) is the forward plan — see [15-Project-Roadmap.md](../../docs/15-Project-Roadmap.md).

**Dependencies.** PHP 8.4; PSR interface packages; `Nizam\Kernel` (for the `Clock` port and tenancy types). No I/O in the spine beyond the logging handlers.

**Public interfaces.** One per subfolder — see each subfolder's README.
