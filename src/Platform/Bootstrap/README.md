# Platform\Bootstrap

**Purpose.** The composition root that assembles the platform: build the container, install providers, boot.

**Responsibilities.**
- `Application` — wraps a `Container`; `configure(basePath)`, `register(ServiceProvider)`, `boot()`, and typed accessors (`container`, `config`, `events`, `logs`, `environment`).
- `CoreServiceProvider` — always-installed provider binding the foundation singletons: `Config`, `Clock`/`SystemClock`, `ListenerProvider`, `EventDispatcher`, `LogManager`, `TenantContext` (PSR ids aliased to concretes).
- `Environment` — enum (`Production`/`Staging`/`Development`/`Testing`) with tolerant `fromString`.

**Dependencies.** `Platform\Container`, `Platform\Config`, `Platform\Event`, `Platform\Logging`, `Platform\Support`, `Kernel\Domain`, `Kernel\Tenancy`; PSR interface packages.

**Public interfaces.** `Application`, `CoreServiceProvider`, `Environment`.
