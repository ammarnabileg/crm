# Kernel\Tenancy

**Purpose.** Carry the current tenant (and per-request identity) through a unit of work in the multi-tenant platform.

**Responsibilities.**
- `TenantContext` — mutable holder of the current `TenantId`: `set`/`get`/`require`/`hasTenant`/`forget`, and `runWithTenant(TenantId, callable)` which always restores the previous tenant (even on exception).
- `RequestContext` — immutable snapshot of `correlationId` + optional `tenantId` + optional `userId`, with `withTenant`/`withUser` copy helpers.

**Dependencies.** `Nizam\Kernel\Domain` (`TenantId`, `UserId`); `Nizam\Platform\Exception`.

**Public interfaces.** `TenantContext`, `RequestContext`.
