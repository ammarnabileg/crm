# Platform\Logging\handlers

**Purpose.** The sinks a `Logger` writes formatted records to.

**Responsibilities.**
- `HandlerInterface` — the narrow contract: `handle(array $record)` where the record is `{level, message, context, channel, timestamp}`.
- `JsonLineHandler` — writes one JSON object per line to a stream resource or a file path (opened lazily, append mode).
- `NullHandler` — discards every record (null-object).

**Dependencies.** `Nizam\Platform\Support\Json`; `Nizam\Platform\Exception`.

**Public interfaces.** `HandlerInterface`, `JsonLineHandler`, `NullHandler`.
