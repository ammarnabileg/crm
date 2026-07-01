# tests/Unit/Kernel/Tenancy

**Purpose.** Unit tests for the tenant context carrier (`Nizam\Kernel\Tenancy`).

**Responsibilities.**
- `TenantContextTest` — set/get/require/forget, `hasTenant`, and `runWithTenant` restoring the previous tenant even when the callable throws.

**Dependencies.** `phpunit/phpunit` ^11; `Nizam\Kernel\Tenancy\TenantContext`; `Nizam\Kernel\Domain\TenantId`.

**Public interfaces.** None — run with `vendor/bin/phpunit`.
