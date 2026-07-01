# src — Nizam source root

**Purpose.** The PSR-4 root (`Nizam\` → `src/`) for the self-built native PHP 8.4 platform. Holds all first-party code for **Nizam — the Bayan AI Operating System** ([ADR-0015](../docs/16-ADR.md#adr-0015)).

**Responsibilities.**
- `Platform/` — `Nizam\Platform\*`: our own framework (replaceable cross-cutting infrastructure behind PSR contracts — container, config, events, logging, and the wider platform).
- `Kernel/` — `Nizam\Kernel\*`: the shared DDD kernel (Domain building blocks, Application CQRS buses, Tenancy). No business rules, no I/O.

**Dependencies.** PHP 8.4 (`declare(strict_types=1)`); PSR **interface** packages only (`psr/container`, `psr/event-dispatcher`, `psr/log`, `psr/clock`, `psr/simple-cache`, `psr/http-*`). Composer for dependency management only — no Laravel/Symfony.

**Public interfaces.** Autoloaded under namespace `Nizam\`; the composition root is `Nizam\Platform\Bootstrap\Application`. See each subfolder's README.
