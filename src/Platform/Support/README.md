# Platform\Support

**Purpose.** Small, pure, dependency-free utilities used throughout the platform.

**Responsibilities.**
- `Result` — typed success/failure value for modelling *expected* failures without throwing.
- `Uuid` — RFC 9562 UUID v7 generation (`v7`, `v7Bytes`) and validation (`isValid`, `isV7`).
- `SystemClock` — production `Clock` port implementation reading real wall-clock time.
- `Assert` — precondition guards that throw `InvalidArgumentException`.
- `Str` — snake/camel/studly/slug and predicate string helpers.
- `Json` — strict encode/decode that throws on malformed input.
- `helpers.php` — a few global convenience functions guarded by `function_exists`.

**Dependencies.** `Nizam\Platform\Exception`; `SystemClock` depends on the `Nizam\Kernel\Domain\Clock` port. No I/O.

**Public interfaces.** `Result`, `Uuid`, `SystemClock`, `Assert`, `Str`, `Json`, and the global functions `str_studly`, `str_snake`, `uuid7`, `value`.
