# 07 — RBAC Engine (نظام الصلاحيات)

The role-based access control engine that decides what every user can do — purely from **permissions, roles, role inheritance and policy gates**, never from a user "type".

## Related Documents

- [08 — Multi-Tenant](08-Multi-Tenant.md) — the tenant context that scopes role resolution.
- [09 — Authentication](09-Authentication.md) — establishes *who* the user is before RBAC decides *what* they may do.
- [10 — Authorization](10-Authorization.md) — how these decisions are enforced at every layer (middleware, controllers, views, model).
- [11 — Permissions Matrix](11-Permissions-Matrix.md) — the authoritative permission-key ↔ role mapping.

---

## Purpose (الهدف)

This document specifies the **RBAC engine** of HalaOps: the catalogue of permissions, the dual nature of roles (global vs tenant), single-parent inheritance, `is_system` protection, the effective-permission resolution algorithm, the policy-gate ("dynamic permissions") layer, the super-admin bypass, and per-request caching.

The engine is implemented by two collaborating services:

- `App\Services\Rbac\AccessControl` (`app/Services/Rbac/AccessControl.php`) — the **decision engine** consulted on every authorization check at runtime.
- `App\Services\Rbac\RbacManager` (`app/Services/Rbac/RbacManager.php`) — the **provisioner** that materialises permissions and roles from `config/rbac.php` into the database (at install time and whenever a company is created).

The single, load-bearing principle of this entire system: **capabilities come ONLY from roles.** There is no `if ($user->type === 'admin')` anywhere in the codebase, and there never will be. A person is a `users` row; everything they can do is the union of the permissions granted by the roles attached to them.

## Why It Exists (سبب وجوده)

HalaOps is sold to thousands of companies, and the same human can simultaneously be an Owner of one company, a Recruiter in another, and a Candidate in a third — while platform staff are Super Admins across all of them. A type column (`users.type = 'recruiter'`) cannot express that: a person is not one thing globally. The only model that fits is **role assignment per context**.

Hard-coded type checks also rot. Every new capability would mean touching scattered `if` statements, and every customer who wants "a recruiter who can also edit jobs" would need a code change. By expressing authorization as **data** (permission keys, role templates in `config/rbac.php`, pivot rows), HalaOps can:

- Add a capability by adding one permission key + assigning it to roles — no `if` statements.
- Let a company build a custom role in a UI without deploying code.
- Keep one auditable source of truth for "who can do what".
- Guarantee that a forgotten branch never silently grants access — the default is **deny**.

The policy-gate layer exists because flat permissions cannot answer context-aware questions like "may this user edit *their own* profile" or "may this recruiter move *an application that belongs to their company*". Those are the "dynamic permissions" — closures that inspect the subject, not just a flag.

## Architecture

### Component responsibilities

| Component | File | Responsibility |
|-----------|------|----------------|
| `AccessControl` | `app/Services/Rbac/AccessControl.php` | Runtime decisions: `allows()`, `denies()`, `hasPermission()`, `hasAnyPermission()`, `hasRole()`, `permissionKeys()`. Resolves effective permissions, walks inheritance, runs policy gates, applies super-admin bypass, caches per request. |
| `RbacManager` | `app/Services/Rbac/RbacManager.php` | Provisioning: `syncPermissions()`, `ensureSuperAdminRole()`, `provisionCompanyRoles()`, `assignGlobalRole()`, `assignMembershipRole()`, `syncRolePermissions()`. Model-free, takes an explicit `Database` so the *same* code runs in the installer and at runtime. |
| `config/rbac.php` | `config/rbac.php` | The permission catalogue and default role templates **as data** (`super_admin_role`, `permissions`, `tenant_roles`). |
| `User` | `app/Models/User.php` | `isSuperAdmin()` — the only role helper on the model; delegates everything heavier to `AccessControl`. |
| `RequirePermission` | `app/Http/Middleware/RequirePermission.php` | Route-level gate (`permission:roles.manage`); calls `access()->allows()`. |
| Helpers | `app/Support/helpers.php` | `access()` returns the container's `AccessControl`; `can($perm, $ctx)` is sugar for `access()->allows($perm, $ctx)`. |

`AccessControl` is a container singleton (alias `access`) constructed with the `AuthManager` (alias `auth`) and `TenantManager` (alias `tenant`). It therefore always knows *who* is asking and *in which tenant*, which is exactly the pair that determines the effective permission set.

### The two kinds of role

Roles live in the `roles` table and are distinguished **solely by `company_id`**:

- **Global role** — `company_id IS NULL`. Assigned to a user directly via the `user_role` pivot. The canonical example is `super-admin`. Global roles apply platform-wide regardless of which tenant is active.
- **Tenant role** — `company_id = <id>`. Assigned through a user's `membership` via the `membership_role` pivot. Examples: `owner`, `admin`, `member` (and the planned `hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `candidate`). Tenant roles only apply inside their own company.

This is why the `companies` table is global and cannot scope to itself, and why the `roles` table carries a nullable `company_id` with a unique `(company_id, slug)` constraint: every company gets its own `owner`, its own `admin`, etc., as independent rows.

### Permission catalogue (authoritative, built)

Permissions are global (one catalogue for the whole platform) and referenced **by key**. The built groups currently enforced (from `config/rbac.php`) are:

| Group | Keys |
|-------|------|
| Dashboard | `dashboard.view` |
| Company | `company.view`, `company.update` |
| Members | `members.view`, `members.invite`, `members.update`, `members.remove` |
| Roles & Permissions | `roles.view`, `roles.manage` |
| Billing | `billing.view`, `billing.manage` |
| AI | `ai.view`, `ai.manage` |
| Settings | `settings.view`, `settings.manage` |

Planned domain groups are added to `config/rbac.php` as each module ships (Jobs, Applications, Interviews, Evaluations, Candidate self-service, Notifications, Files, Platform). The full present-and-planned list, and which role gets which key, is the subject of [11 — Permissions Matrix](11-Permissions-Matrix.md). HalaOps forbids unused permissions: a key exists only when something actually enforces it.

### Default role templates (data)

`config/rbac.php` ships three system tenant-role templates today — `owner` (permissions `'*'`), `admin` (everything except `billing.manage` and ownership), `member` (read-mostly) — plus the global `super-admin`. The remaining personas (`hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `candidate`) are defined as data in the same file as their domain permissions land. Because the templates are data, a deployment can add or re-tune a default role by editing config and re-seeding, and a company can clone/extend a role at runtime through the role editor gated by `roles.manage`.

## Workflow

### Provisioning (write path)

1. **Install** (`InstallManager` → `RbacManager`): `syncPermissions()` upserts the catalogue into `permissions`; `ensureSuperAdminRole()` creates the `super-admin` global role (`company_id = NULL`, `is_system = 1`) and grants it *every* permission id.
2. **Company creation** (`CompanyService::create()` → `RbacManager::provisionCompanyRoles($companyId)`): inside the same DB transaction that creates the company and the owner membership, every template in `config/rbac.php`'s `tenant_roles` becomes a `roles` row for that company, its permissions are written to `permission_role`, and `parent` links are resolved into `parent_id`. The owner membership is then granted the `owner` role via `membership_role`.
3. **Assignment**: `assignGlobalRole($userId, $roleId)` writes `user_role`; `assignMembershipRole($membershipId, $roleId)` writes `membership_role`. Both are idempotent (guarded by an existence check).

### Resolution (read path)

```mermaid
flowchart TD
    A["access()->allows(ability, context)"] --> B{User authenticated?}
    B -- No --> DENY["return false"]
    B -- Yes --> C{user.isSuperAdmin()?}
    C -- Yes --> ALLOW["return true (bypass)"]
    C -- No --> D{Policy gate defined<br/>for ability?}
    D -- Yes --> E["run gate(user, context)<br/>-> bool"]
    D -- No --> F["effectivePermissions(user)"]
    F --> G{Cached for<br/>userId:companyId?}
    G -- Yes --> K["lookup key in cache map"]
    G -- No --> H["resolveRoleIds(user, companyId)"]
    H --> H1["global roles via user_role"]
    H --> H2["tenant roles via active<br/>membership + membership_role"]
    H1 --> I["expandWithAncestors()<br/>walk parent_id chains"]
    H2 --> I
    I --> J["SELECT permissions.key<br/>JOIN permission_role<br/>WHERE role_id IN (...)"]
    J --> CACHE["store map in per-request cache"]
    CACHE --> K
    K --> RESULT{"key present?"}
    RESULT -- Yes --> ALLOW2["return true"]
    RESULT -- No --> DENY2["return false"]
```

Step by step, as implemented in `AccessControl::allows()`:

1. Resolve the current `User` from `AuthManager`. If `null` (guest) → **deny**.
2. If `User::isSuperAdmin()` → **allow** (the bypass; see Business Rules).
3. If a **policy gate** is registered for the ability under `define()`, run its closure with `(User $user, mixed $context)` and return its boolean. Gates take precedence over flat permissions because they can express context.
4. Otherwise delegate to `hasPermission($user, $ability)`, which looks the key up in `effectivePermissions($user)`.

`effectivePermissions()` computes the **union** described in §6 of the canonical context:

1. Cache key = `"{userId}:{companyId}"` (companyId `0` when no active tenant). Return early on a hit.
2. `resolveRoleIds($user, $companyId)`:
   - **(a)** global role ids from `user_role WHERE user_id = ?`;
   - **(b)** if a tenant is active, the `memberships.id` for `(user, company)` with `status = 'active'`, then the role ids from `membership_role WHERE membership_id = ?`;
   - merge, de-dupe, then `expandWithAncestors()`.
3. `expandWithAncestors()` performs a breadth-first walk of `roles.parent_id`, accumulating every ancestor exactly once (cycle-safe via a `resolved` set), so a child role inherits all of its parent's permissions.
4. A single `SELECT DISTINCT permissions.key … JOIN permission_role … WHERE permission_role.role_id IN (:ids)` flattens those roles into a permission map `[key => true]`, which is cached and returned.

## Business Rules

1. **Capabilities derive only from roles + permissions + memberships.** No user-type column or table participates in any decision. (Reinforced by the single `users` table; see [09 — Authentication](09-Authentication.md).)
2. **Global vs tenant is decided by `roles.company_id`.** `NULL` ⇒ global (assigned via `user_role`); a value ⇒ tenant (assigned via `membership_role`). Uniqueness is `(company_id, slug)`.
3. **Effective permissions = union of (global user roles) ∪ (active-membership tenant roles), each expanded up its `parent_id` chain.** A user with no roles has an empty permission set and is denied everything.
4. **Only the *active* membership contributes.** Tenant role resolution uses the currently active `company_id` (from `TenantManager`) and requires `memberships.status = 'active'`. Roles from other companies a user belongs to never leak into the current tenant's decision.
5. **Super admins bypass every permission check.** `isSuperAdmin()` returning true short-circuits `allows()` to `true` before gates or permission lookups run.
6. **Policy gates beat flat permissions.** If an ability has a registered gate, the gate decides — enabling context-aware ("own record", "in my company") rules that a boolean flag cannot represent.
7. **`is_system` roles are protected.** Roles seeded with `is_system = 1` (`super-admin`, `owner`, `admin`, `member`) must not be deletable or renamable by the role editor; the editor (gated by `roles.manage`) may only edit non-system roles or clone system ones. This guarantees a company can never delete its `owner` role and lock itself out.
8. **`priority` orders roles** for display and for any "highest role wins" UI labelling; it does **not** affect permission resolution, which is a pure union.
9. **Middleware uses any-of semantics.** `permission:a,b` (in `RequirePermission`) allows the request if the user has *any* of the listed keys.
10. **Permissions are referenced by key, defined once.** Adding a key is an edit to `config/rbac.php` + a re-sync; removing a key requires removing its enforcement first (no orphan permissions).

## Database Relations

Consistent with §11 of the canonical context. The RBAC engine touches:

| Table | Key columns | Notes |
|-------|-------------|-------|
| `roles` | `id`, `company_id` (FK→`companies` CASCADE, **NULL = global**), `parent_id` (FK→`roles` SET NULL), `slug`, `is_system`, `priority` | `UNIQUE(company_id, slug)`, `INDEX(parent_id)`. Single-parent inheritance via `parent_id`. |
| `permissions` | `id`, `key` (UNIQUE), `name`, `group` | `INDEX(group)`. The global catalogue; referenced by `key` in code. |
| `permission_role` | `role_id` (FK→`roles` CASCADE), `permission_id` (FK→`permissions` CASCADE) | `PK(role_id, permission_id)`. What each role grants. |
| `membership_role` | `membership_id` (FK→`memberships` CASCADE), `role_id` (FK→`roles` CASCADE) | `PK(membership_id, role_id)`. Tenant-role assignment. |
| `user_role` | `user_id` (FK→`users` CASCADE), `role_id` (FK→`roles` CASCADE) | `PK(user_id, role_id)`. Global-role assignment. |
| `memberships` | `id`, `company_id`, `user_id`, `status` | `UNIQUE(company_id, user_id)`; only `status = 'active'` contributes roles. |

Cascade behaviour matters for correctness: deleting a company cascades its tenant `roles`, which cascades the relevant `permission_role`/`membership_role` rows; deleting a user cascades their `user_role`/`membership` rows. No orphaned grants survive.

## Permissions

RBAC is the system that *defines* permissions, but it is itself gated:

- **`roles.view`** — view roles and their permissions (the role list / role editor read view).
- **`roles.manage`** — create, edit, delete roles and assign permissions. Subject to the `is_system` protection rule above.
- Assigning roles to members is part of **`members.update`** (changing a member's roles is editing a member).

`super-admin` (global) holds every permission including these; `owner` holds them via the `'*'` template; `admin` holds `roles.view` + `roles.manage`; `member` holds only `roles.view` is **not** granted (member has read-mostly scope — see the matrix). The authoritative grants are in [11 — Permissions Matrix](11-Permissions-Matrix.md).

## Validation

When roles/permissions are created or edited (role editor, gated by `roles.manage`):

- `name` — required, string, max 120 (matches `roles.name VARCHAR(120)`).
- `slug` — required, lowercase-kebab, unique within the company (`UNIQUE(company_id, slug)`); auto-derived from name if omitted.
- `description` — optional, max 255.
- `parent_id` — optional; must reference an existing role **in the same company** (or be null); must not create a cycle (the chosen parent may not be a descendant of the role being edited).
- `permissions[]` — each value must be a key that exists in the `permissions` catalogue; unknown keys are rejected (mirrors `RbacManager::provisionCompanyRoles()`, which filters unknown keys via `permissionKeyToId()`).
- `is_system` — never settable through the UI; only the provisioner sets it.
- `priority` — integer, default 0.

`RbacManager::syncRolePermissions()` is the write primitive: it deletes existing `permission_role` rows for the role and re-inserts the de-duplicated set, so saving a role is an idempotent replace.

## Edge Cases

- **User with no roles** → empty permission map → denied everything; UI shows only public/own-profile affordances. Handled naturally (no special-casing).
- **No active tenant** (fresh super admin, or a user who hasn't selected a company) → `companyId` is `null`; only global roles contribute. Cache key uses `'0'` for the company segment so platform-context permissions cache separately from any tenant.
- **Suspended/invited membership** → excluded by `status = 'active'` in `resolveRoleIds()`; an invited-but-not-joined user gets none of that company's tenant roles.
- **Cyclic `parent_id`** (data corruption) → `expandWithAncestors()` is cycle-safe: the `resolved` set prevents infinite loops; the cycle simply resolves to its member set once.
- **Orphaned `parent_id`** (parent deleted) → the FK is `ON DELETE SET NULL`, so the child's `parent_id` becomes `NULL` and it simply stops inheriting; no error.
- **Deleting a system role** → blocked by the `is_system` rule before any DB write.
- **Stale permissions after a role edit mid-request** → see Performance: the per-request cache must be flushed (`flushCache()`) after any role/permission/membership mutation so the same request re-resolves.
- **Two roles granting the same key** → harmless; the union de-dupes via the map and `SELECT DISTINCT`.

## Security

- **Default deny.** `allows()` returns `false` for guests and for any key not present in the effective map; there is no implicit allow.
- **No type-based escalation.** Because decisions never read a "type", a compromised or malformed user record cannot grant privileges by flipping a string; privileges require pivot rows referencing real roles.
- **Super-admin bypass is narrow and explicit.** It depends solely on holding a *global* `super-admin` role (`user_role` → `roles.slug = 'super-admin'` with `company_id IS NULL`), checked by a single query in `User::isSuperAdmin()`. There is no environment flag or hidden backdoor.
- **`is_system` protection** prevents privilege lock-out and prevents tampering with the super-admin/owner definitions.
- **Parameterised throughout.** All resolution queries use the `QueryBuilder` (bound parameters, backtick-quoted identifiers) — no string-built SQL, so permission keys/ids cannot be injected.
- **Tenant correctness is a security property.** Resolution is bound to the active `company_id`; combined with model-layer scoping ([08 — Multi-Tenant](08-Multi-Tenant.md)) this prevents a user from exercising one company's roles against another's data.
- **Audit.** Role assignment/changes and sensitive RBAC events should be written to `activity_log` (actor, subject, ip) per §13.

## Performance

- **Per-request cache.** `AccessControl::$cache` keys the fully-resolved `[key => true]` map by `"userId:companyId"`. The first `can()` in a request pays for resolution; every subsequent check (controllers, repeated view gates) is an array lookup. A page that renders dozens of `can()`-gated elements performs **one** resolution, not dozens.
- **Bounded queries.** Resolution is: one `user_role` read, one `memberships` lookup, one `membership_role` read, an ancestor walk (one tiny `parent_id` read per distinct role — chains are short, typically depth 1–2), and one final flatten join. All hit indexed columns (`user_role` PK, `memberships UNIQUE(company_id,user_id)`, `membership_role` PK, `permission_role` PK, `roles.parent_id` index).
- **`flushCache()`** must be called after mutating roles/permissions/memberships within a request so the cache cannot serve stale grants.
- **Future optimisation.** The ancestor walk could be replaced by a single recursive CTE on MySQL 8 if role hierarchies ever deepen; and the resolved map could be memoised in the session/Redis across requests with an invalidation key bumped on RBAC writes (see Future Expansion).

## Testing

**Unit (`AccessControl`):**
- Guest → `allows()` is false for every ability.
- Super admin → `allows()` true for any ability, including undefined ones, without touching `permission_role`.
- Single tenant role with key `X` → `can('X')` true, `can('Y')` false.
- Inheritance: child role with parent that holds `X` → child user has `X`.
- Union: user with global role granting `A` + tenant role granting `B` → both true.
- Cache: second call resolves from cache (assert query count / spy); `flushCache()` forces re-resolution.
- `hasAnyPermission(['a','b'])` reflects any-of.

**Unit (`RbacManager`):**
- `syncPermissions()` is idempotent (running twice yields the same rows, updates names).
- `ensureSuperAdminRole()` grants *all* permission ids and is idempotent.
- `provisionCompanyRoles()` creates the configured roles, wires `parent_id`, and resolves `'*'` to all permissions.
- Unknown permission keys in a template are filtered out, not inserted.

**Feature / security:**
- `permission:roles.manage` middleware → 403 for `member`, 200 for `admin`/`owner`.
- Switching active company changes the effective permission set (roles from company A do not apply in company B).
- Attempt to delete an `is_system` role → rejected.
- A user with an `invited` (non-active) membership receives none of that company's permissions.

## Future Expansion

- **More personas as data.** `hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `candidate` are added to `config/rbac.php` alongside their domain permission groups — no engine changes.
- **Custom roles per company** via the `roles.manage` editor (clone a system role, tweak permissions) — already supported by the data model and `syncRolePermissions()`.
- **Deeper hierarchies** — `parent_id` already supports multi-level inheritance; swap the BFS for a recursive CTE if depth grows.
- **Cross-request permission caching** — promote the per-request map to session/Redis with an invalidation token, for very hot dashboards.
- **Permission-conditioned policy gates at scale** — register more gates via `AccessControl::define()` (e.g. `evaluations.manage` for "only the assigned evaluator") as the recruitment domain lands; see [10 — Authorization](10-Authorization.md).
- **API parity** — the same `AccessControl` backs token-authenticated `/api/v1` requests (§12), so REST and web share one authorization brain.

## Open Questions

None at this time. The engine, its data model, and its provisioning path are fully implemented in `AccessControl`, `RbacManager`, and `config/rbac.php`; the only deltas are additive (new permission keys/roles for modules not yet shipped), which the data-driven design already accommodates.
