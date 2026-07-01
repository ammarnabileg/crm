# Platform\Logging

**Purpose.** PSR-3 logging split into named channels, each fanning records out to configurable handlers.

**Responsibilities.**
- `Logger` — PSR-3 logger (extends `AbstractLogger`); validates levels, interpolates `{placeholders}`, timestamps via the `Clock` port, and dispatches records to handlers.
- `LogManager` — creates/caches one logger per channel; built-in channels: `app`, `activity`, `performance`, `error`, `security`.
- `handlers/` — the record sinks (see its own README).

**Dependencies.** `psr/log`; `Nizam\Kernel\Domain\Clock`; `Nizam\Platform\Support\Json`.

**Public interfaces.** `Logger` (`Psr\Log\LoggerInterface`), `LogManager::channel()`.
