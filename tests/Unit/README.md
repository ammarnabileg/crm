# tests/Unit

**Purpose.** Fast, isolated unit tests for the Wave-1 foundation spine. Mirrors the `src/` tree under `Nizam\Tests\Unit\...` and runs as the PHPUnit "Unit" test suite.

**Responsibilities.** Cover each foundation component with meaningful assertions:
- Support: `ResultTest`, `UuidTest` (v7 version/variant, uniqueness, ordering, validation).
- Container: `ContainerTest` (bind/singleton/instance/autowire/call, not-found, unresolvable scalar, circular).
- Config: `ConfigTest`, `EnvTest` (casting, `required`).
- Event: `EventDispatcherTest` (priority, stable order, stoppable, subtype matching).
- Logging: `LoggerTest` (JSON line output, interpolation, channels, invalid level).
- Kernel: `IdentifierTest` (equality/validation, aggregate events), `SimpleCommandBusTest`, `TenantContextTest`.
- Bootstrap: `ApplicationTest` (core singletons, boot idempotency, environment parsing).

**Dependencies.** `phpunit/phpunit` ^11 (attribute-based); the `Nizam\` autoloader.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
