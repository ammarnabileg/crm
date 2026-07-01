# Platform\Container

**Purpose.** A dependency-free PSR-11 dependency-injection container with constructor autowiring, plus the service-provider abstraction that wires the platform together.

**Responsibilities.**
- `Container` — `bind`/`singleton`/`instance`/`get`/`has`/`make`/`call`; reflection-based autowiring; circular-dependency detection.
- `ServiceProvider` — abstract two-phase registration (`register` then `boot`).
- `ContainerException` / `NotFoundException` — PSR-11 error types.

**Dependencies.** `psr/container`; `Nizam\Platform\Exception`. Uses PHP Reflection.

**Public interfaces.** `Container` (implements `Psr\Container\ContainerInterface`), `ServiceProvider`, `ContainerException`, `NotFoundException`.
