# 11 — Permissions Matrix (مصفوفة الصلاحيات)

The authoritative mapping of every permission key (built + planned) to every default role — the single reference for "who can do what" in HalaOps.

## Related Documents

- [07 — RBAC](07-RBAC.md) — the engine that resolves these grants (roles, inheritance, super-admin bypass, gates).
- [10 — Authorization](10-Authorization.md) — where these grants are enforced (middleware, controllers, gates, views, model scope).
- [19 — Candidate Journey](19-Candidate-Journey.md) · [20 — Recruiter Journey](20-Recruiter-Journey.md) · [21 — HR Journey](21-HR-Journey.md) · [22 — SuperAdmin Journey](22-SuperAdmin-Journey.md) — the personas these roles model.

---

## Purpose (الهدف)

This document is the **authoritative permission matrix**: every permission key from the canonical catalogue (§6 — built and planned) mapped to each default role — `super-admin`, `owner`, `admin`, `hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `member`, `candidate`. It is the reference a developer consults before guarding a route with `permission:…`, and the spec the seeders/`config/rbac.php` templates must satisfy.

Two columns are grounded directly in shipped data:

- **`super-admin`** holds **every** permission (the global role provisioned by `RbacManager::ensureSuperAdminRole()`), and additionally **bypasses** all checks via `AccessControl::allows()`.
- **`owner`**, **`admin`**, **`member`** match the `tenant_roles` templates in `config/rbac.php` exactly for the built groups (`owner` = `'*'`).

The remaining tenant roles (`hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `candidate`) are defined as data in `config/rbac.php` as their domain permissions ship; their grants below are the canonical target consistent with §6. Keys are written **exactly** as in the catalogue.

## Why It Exists (سبب وجوده)

Capabilities in HalaOps come only from roles ([07 — RBAC](07-RBAC.md)). For that to be safe and consistent across thousands of companies, there must be one place that states precisely which keys each default role carries — otherwise seeders, the role editor, route guards, and documentation drift apart, and a forgotten grant becomes either a lock-out or an over-privilege bug. This matrix is that single source. It also makes least-privilege auditable: each role's footprint is visible at a glance, so reviewers can confirm, for example, that `interviewer` cannot reject candidates and `recruiter` cannot change billing.

## Architecture

The matrix is the human-readable projection of three data sources, all consistent with each other:

- **`config/rbac.php`** — `permissions` (the catalogue) and `tenant_roles` (default grants) **as data**.
- **Database** — `permissions`, `roles`, `role_permissions` after `RbacManager` provisioning.
- **This document** — the canonical mapping, including planned keys not yet in `config/rbac.php`.

Legend: **✓** = granted, **–** = not granted. `super-admin` is global (`workspace_id NULL`, assigned via `user_roles`) and also bypasses every check; all others are **tenant** roles (assigned via `membership_roles`). Inheritance (`parent_id`) and the union of global + tenant roles are described in [07 — RBAC](07-RBAC.md); the matrix shows each role's **direct** intended grant.

## The Matrix

> Columns: SA = super-admin, OWN = owner, ADM = admin, HRM = hr-manager, REC = recruiter, HM = hiring-manager, INT = interviewer, MEM = member, CND = candidate.
> Status: **Built** = enforced today (in `config/rbac.php`); **Planned** = canonical target, added to config as the module ships.

### Built permissions (enforced today)

| Permission key | Group | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|-------|--------|----|----|----|----|----|----|----|----|----|
| `dashboard.view` | Dashboard | Built | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| `workspace.view` | Workspace | Built | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| `workspace.update` | Workspace | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `members.view` | Members | Built | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ | – |
| `members.invite` | Members | Built | ✓ | ✓ | ✓ | ✓ | – | – | – | – | – |
| `members.update` | Members | Built | ✓ | ✓ | ✓ | ✓ | – | – | – | – | – |
| `members.remove` | Members | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `roles.view` | Roles & Permissions | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `roles.manage` | Roles & Permissions | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `billing.view` | Billing | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `billing.manage` | Billing | Built | ✓ | ✓ | – | – | – | – | – | – | – |
| `ai.view` | AI | Built | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | ✓ | – |
| `ai.manage` | AI | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `settings.view` | Settings | Built | ✓ | ✓ | ✓ | ✓ | – | – | – | ✓ | – |
| `settings.manage` | Settings | Built | ✓ | ✓ | ✓ | – | – | – | – | – | – |
| `system.manage` | System | Built | ✓ | – | – | – | – | – | – | – | – |

> The SA / OWN / ADM / MEM columns above reproduce `config/rbac.php` exactly: `owner` = `'*'` (all tenant ✓); `admin` = all built keys **except** `billing.manage` and `system.manage`; `member` = `dashboard.view`, `workspace.view`, `members.view`, `ai.view`, `settings.view`. `system.manage` is a **platform-level** capability exercised with no active tenant (it gates the `/system/*` operations console — diagnostics, maintenance, backups, env editor, log viewer); it is held by `super-admin` only. HRM/REC/HM/INT are not yet present in config (today they would be modelled as `member` or custom roles); their values are the canonical target for when those templates are added.

### Planned permissions — Jobs

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `jobs.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `jobs.create` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |
| `jobs.update` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |
| `jobs.delete` | Planned | ✓ | ✓ | ✓ | ✓ | – | – | – | – | – |
| `jobs.publish` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |

### Planned permissions — Applications

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `applications.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `applications.update` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |
| `applications.move` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |
| `applications.reject` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |
| `applications.export` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |

### Planned permissions — Interviews

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `interviews.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `interviews.schedule` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – |
| `interviews.conduct` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `interviews.cancel` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – |

### Planned permissions — Evaluations

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `evaluations.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `evaluations.create` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – |
| `evaluations.manage` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – |

### Planned permissions — Candidate self-service (candidate portal)

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `candidate.apply` | Planned | ✓ | – | – | – | – | – | – | – | ✓ |
| `candidate.profile` | Planned | ✓ | – | – | – | – | – | – | – | ✓ |

### Planned permissions — Notifications

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `notifications.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Planned permissions — Files

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `files.view` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `files.upload` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `files.delete` | Planned | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – | – |

### Planned permissions — Platform (super-admin global)

These are **global** permissions, exercised in platform context (no tenant) and held only by `super-admin`. No tenant role grants them.

| Permission key | Status | SA | OWN | ADM | HRM | REC | HM | INT | MEM | CND |
|----------------|--------|----|----|----|----|----|----|----|----|----|
| `platform.companies.view` | Planned | ✓ | – | – | – | – | – | – | – | – |
| `platform.companies.manage` | Planned | ✓ | – | – | – | – | – | – | – | – |
| `platform.users.view` | Planned | ✓ | – | – | – | – | – | – | – | – |
| `platform.users.manage` | Planned | ✓ | – | – | – | – | – | – | – | – |
| `platform.plans.manage` | Planned | ✓ | – | – | – | – | – | – | – | – |
| `platform.diagnostics` | Planned | ✓ | – | – | – | – | – | – | – | – |

## Role intent (وصف الأدوار)

Each role expresses a persona from §1. Personas are **role assignments**, never user types — the same person can be `owner` in one workspace and `candidate` in another.

- **super-admin (global, `is_system`)** — platform staff. Holds every permission *and* bypasses checks in `AccessControl::allows()`. Operates platform-wide (no tenant required) and reaches cross-tenant data only via `withoutTenantScope()` and the `platform.*` permissions. The only role with `workspace_id NULL`.

- **owner (`is_system`)** — the workspace's founder/owner; grant template `'*'` (every tenant permission, including `billing.manage` and `roles.manage`). There is exactly one ownership concept per workspace (`workspaces.owner_id`); the `owner` role is protected from deletion so a workspace can never lock itself out.

- **admin (`is_system`)** — runs the workspace day to day. Everything the owner can do **except** `billing.manage` and ownership transfer. Manages members, roles, AI, settings, and (planned) the full recruitment domain.

- **hr-manager** — leads recruiting operations: manages jobs, the application pipeline, interviews and evaluations end to end, invites/edits members, and views settings/AI — but does **not** control billing, roles, ownership, or workspace profile editing. The senior recruitment persona below admin.

- **recruiter** — does the hands-on hiring work: creates/updates/publishes jobs, moves and rejects applications, schedules and conducts interviews, writes and manages evaluations, exports pipelines. No member administration beyond viewing, no billing/roles/settings management.

- **hiring-manager** — the line manager for a role: reviews their pipeline (`applications.view`), participates in interviews (`interviews.schedule/conduct/cancel`), and gives evaluations (`evaluations.create/manage`), but does **not** create jobs or reject candidates (those stay with recruiters/HR). Read access to jobs/interviews relevant to their hiring.

- **interviewer** — invited to conduct specific interviews and submit scorecards: `interviews.view/conduct`, `evaluations.view/create`, and view the jobs/applications/candidates they are interviewing. Cannot schedule/cancel, move applications, reject, or manage jobs — the narrowest recruitment role.

- **member (`is_system`)** — a standard employee with no recruitment duties: `dashboard.view`, `workspace.view`, `members.view`, `ai.view`, `settings.view` (read-mostly), plus the universal `notifications.view`/`files.*` self-service. The default safe baseline for someone added to a workspace.

- **candidate** — an external applicant using the candidate portal. Holds only `candidate.apply` and `candidate.profile` (plus universal notifications/files for their own materials). A candidate is a normal `users` row whose `applications` link them to a workspace's `jobs`; they hold **no** internal workspace permissions — `dashboard.view` is intentionally **–** because candidates use the portal, not the workspace dashboard.

## Workflow

How a row in this matrix becomes a live grant:

1. A permission key is added to `config/rbac.php`'s `permissions` (catalogue) **only when something enforces it** (no orphan permissions).
2. The relevant `tenant_roles` templates list the key (or `'*'` for `owner`); `super-admin` always gets all keys via `ensureSuperAdminRole()`.
3. `RbacManager::syncPermissions()` upserts the catalogue; `provisionWorkspaceRoles()` writes each role's `role_permissions` rows per workspace; `ensureSuperAdminRole()` re-grants all to super-admin.
4. At runtime, `AccessControl` resolves a user's effective set (union of global + active-membership roles, expanded up `parent_id`) and the enforcement layers in [10 — Authorization](10-Authorization.md) check the key.
5. Companies may diverge from the defaults via the role editor (gated by `roles.manage`) — this matrix documents the **defaults**, not a ceiling.

## Business Rules

1. **Keys are exact and global.** A permission key is spelled identically everywhere (catalogue, config, code, this matrix) and defined once in `permissions`.
2. **`super-admin` = all + bypass.** It holds every key and short-circuits checks; no tenant role ever gets `platform.*`.
3. **`owner` = `'*'`.** The only tenant role with the full tenant permission set, including `billing.manage`.
4. **`admin` ⊂ `owner`.** Admin equals owner minus `billing.manage` and ownership transfer.
5. **Least privilege downward.** `hr-manager` ⊋ `recruiter` ⊋ {`hiring-manager`, `interviewer`} for recruitment scope, with `interviewer` the narrowest; `member` is read-mostly internal; `candidate` is external self-service only.
6. **Rejection is restricted.** `applications.reject` is held only by `recruiter`, `hr-manager`, `admin`, `owner`, `super-admin` — never `hiring-manager`/`interviewer`.
7. **Job authorship is restricted.** `jobs.create/update/publish` exclude `hiring-manager`/`interviewer`; `jobs.delete` is HR/admin/owner only.
8. **Universal self-service.** `notifications.view` and `files.view/upload` are held by every role (including `candidate`) so anyone can see their notifications and manage their own files; `files.delete` is restricted to senior roles.
9. **Defaults, not limits.** Companies can customise roles via `roles.manage`; `is_system` roles (`super-admin`, `owner`, `admin`, `member`) cannot be deleted.
10. **Candidate isolation.** `candidate.*` is held only by `candidate` and `super-admin`; internal roles never need it, and candidates never hold internal keys.

## Database Relations

Consistent with §11 and [07 — RBAC](07-RBAC.md):

| Table | Role in the matrix |
|-------|--------------------|
| `permissions` (`key` UNIQUE, `module_id` → `system_modules`, `action`) | the rows of this matrix; one row per key. |
| `roles` (`workspace_id`, `slug`, `is_system`, `parent_id`) | the columns; `super-admin` has `workspace_id NULL`, the rest are per-workspace. |
| `role_permissions` (PK `role_id,permission_id`) | each ✓ is a row here. |
| `membership_roles` / `user_roles` | how a user receives a column's grants (tenant vs global). |
| `memberships` (`status='active'`) | only active memberships project their role's column onto a user. |

## Permissions

This document *is* the permission reference; the keys that gate the act of editing it are `roles.view` (to read roles/permissions) and `roles.manage` (to change role grants), held by `admin`, `owner`, and `super-admin`. Changing any company's actual grants away from this default is itself an authorized action requiring `roles.manage`.

## Validation

When applying or editing grants (seed/config or role editor):

- Every key assigned to a role must exist in `permissions` (`RbacManager` filters unknown keys; the editor rejects them).
- `super-admin` must end up with the full catalogue (post-`ensureSuperAdminRole()` invariant — testable).
- `owner` must resolve to the full **tenant** set (`'*'`).
- No tenant role may be granted `platform.*` keys.
- Role slugs are unique per workspace (`UNIQUE(workspace_id, slug)`); the nine default slugs are reserved.
- The matrix and `config/rbac.php` must agree for built keys (a test should diff them).

## Edge Cases

- **A planned key referenced before its module ships** → it must first be added to the catalogue; until then no role can hold it (FK integrity in `role_permissions`). The "Planned" rows here are the target, not yet live.
- **Custom company role overlapping a default** → allowed; this matrix describes defaults only. The role editor governs overrides.
- **User with two roles granting overlapping keys** → union de-dupes; no conflict (a key is either present or not).
- **`candidate` who is also an employee** → they hold *both* roles via memberships; effective permissions are the union, so an employee-applicant sees both portals appropriately.
- **`hiring-manager` needing to reject** → by policy they request rejection through a recruiter/HR; if a company disagrees, it grants `applications.reject` to its own custom/edited role (not the default).
- **Removing a built key from the catalogue** → forbidden until its enforcement is removed (no orphan permissions; would break `role_permissions` references).

## Security

- **Least privilege by construction:** each role's footprint is explicit and minimal; reviewers can audit over-grants from this single table.
- **No `platform.*` leakage:** platform permissions are confined to `super-admin`; combined with the model scope ([08 — Multi-Tenant](08-Multi-Tenant.md)), tenant roles cannot reach cross-tenant operations.
- **Sensitive actions concentrated:** billing (`billing.manage`), role administration (`roles.manage`), member removal (`members.remove`), candidate rejection (`applications.reject`), and deletions (`jobs.delete`, `files.delete`) are held only by senior roles — limiting blast radius of a compromised low-privilege account.
- **Candidate containment:** the external `candidate` role carries no internal keys, so a candidate account cannot enumerate or act on company internals.
- **Auditable changes:** edits to grants (via `roles.manage`) and sensitive uses are recorded in `activity_log` ([34 — Security](34-Security.md)).
- **Defaults safe by default:** new members get `member` (read-mostly), not an over-privileged role.

## Performance

- The matrix has no runtime cost itself; it is realised as `role_permissions` rows resolved by the cached `AccessControl` engine (one resolution per `userId:workspaceId` per request — see [07 — RBAC](07-RBAC.md)).
- Keeping role grants reasonably small keeps the flatten-join (`permissions JOIN role_permissions WHERE role_id IN (...)`) cheap; all involved columns are indexed (`permissions.key`, `role_permissions` PK).
- Grouping permissions by `permissions.module_id` (indexed, → `system_modules`) powers an efficient role-editor UI that renders the catalogue without scanning.

## Testing

**Unit / data-integrity:**
- After provisioning, `super-admin` holds every key in `permissions`.
- `owner` holds the full tenant set; `admin` = owner minus `billing.manage`; `member` matches the five read-mostly keys.
- No tenant role holds any `platform.*` key.
- `config/rbac.php` built-key grants equal this matrix's Built rows (diff test).

**Feature / security (per role):**
- `recruiter` can `applications.move`/`applications.reject` but cannot `billing.manage` or `roles.manage`.
- `hiring-manager`/`interviewer` cannot `applications.reject` or `jobs.create`.
- `interviewer` can `interviews.conduct` + `evaluations.create` but not `interviews.schedule`/`cancel`.
- `candidate` can `candidate.apply`/`candidate.profile` but no internal key (e.g. 403 on a `members.view` route).
- `member` can `dashboard.view` but not `members.invite`/`settings.manage`.
- Switching tenant changes which column applies (a recruiter in A is a member in B, etc.).

## Future Expansion

- **Ship the planned groups** by adding their keys to `config/rbac.php` and the corresponding role templates (`hr-manager`, `recruiter`, `hiring-manager`, `interviewer`, `candidate` become full templates) — this matrix is their acceptance spec.
- **Per-company custom roles** via the role editor, with this matrix as the documented baseline.
- **Permission bundles / categories** for faster role authoring as the catalogue grows.
- **Environment-specific defaults** (e.g. agencies vs in-house) shipped as alternate `tenant_roles` profiles.
- **Automated drift detection** in CI comparing `config/rbac.php`, the seeded DB, and this document.
- **Domain-specific gates** complementing flat keys (e.g. "interviewer only for interviews they're assigned to") registered via `AccessControl::define()` — see [10 — Authorization](10-Authorization.md).

## Open Questions

- Whether `hiring-manager` should receive `applications.reject` by default is a product decision; the matrix currently withholds it (recruiters/HR reject) and lets companies grant it via `roles.manage`. To be confirmed with the recruitment-domain spec ([25 — Application Lifecycle](25-Application-Lifecycle.md)).
- Exact split of `evaluations.manage` between `recruiter` and `hiring-manager` once the scorecard workflow is finalised (current default: both create; recruiter/HR manage). Otherwise none at this time.
