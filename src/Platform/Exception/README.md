# Platform\Exception

**Purpose.** The platform's typed exception hierarchy for *unexpected* / programmer / infrastructure errors. Expected business failures use `Support\Result` instead.

**Responsibilities.**
- `PlatformException` — base runtime exception; an outer boundary catches it to produce safe responses.
- `InvalidArgumentException` — precondition violation; extends the SPL type.
- `ConfigException` — missing/malformed configuration or a required env var.

**Dependencies.** PHP SPL only (`\RuntimeException`, `\InvalidArgumentException`).

**Public interfaces.** `PlatformException`, `InvalidArgumentException`, `ConfigException`.
