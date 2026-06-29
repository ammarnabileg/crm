# SYSTEM PERMISSIONS — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_CATALOG.md` §3, `PERMISSION_MODEL.md`.

---

## 1. What System Permissions Are

System permissions are the `system.*` keys in the authoritative registry
(`PERMISSION_CATALOG.md` §3). They govern the **platform itself** — the global
control plane that sits above every tenant — rather than the work that happens
inside any single workspace.

- A **System Owner** is **not** a separate account type. Per `USER_MODEL.md` §2,
  there is exactly **one** human account type — `User` — and "System Owner" is a
  **capability**: a `User` that holds one or more `system.*` permissions. The
  capability is represented purely by permissions attached to that `User`
  (surfaced as the `is_system_owner` flag), **never** by a separate table, class,
  or hard-coded role.
- System permissions are evaluated under the **Platform Context** only (see
  `WORKSPACE_MODEL.md` §7). They unlock the System Administration console and the
  global, cross-tenant capabilities of the operations, commerce, intelligence,
  integration, and audit layers.
- The platform follows the same **deny-by-default** rule as everything else
  (`PERMISSION_MODEL.md` §5): a `User` that does not hold a given `system.*` key
  **MUST** be denied the corresponding platform action, server-side, regardless
  of UI state.

System permissions are distinct from workspace permissions in scope, visibility,
and effect. Workspace permissions (`PERMISSION_CATALOG.md` §2,
`WORKSPACE_PERMISSIONS.md`) authorize action **inside one tenant** and never
reach across tenants. System permissions authorize action **across all tenants
and the platform fabric**, and never substitute for a workspace grant.

> **Code checks keys, never role names** (`PERMISSION_MODEL.md` §1). Nothing in
> this document authorizes a code path to branch on the string "System Owner" or
> any role name. Every check below is a check of a `system.*` **key**.

---

## 2. The `system.*` Catalog (authoritative)

The following table reproduces `PERMISSION_CATALOG.md` §3 verbatim as the source
of truth, then expands each key. **No key outside this list exists**; adding a
platform capability means adding a key to the catalog first (`PERMISSION_MODEL.md`
§8, invariant 5), not editing the permission engine.

| Key | Allows | Module |
|---|---|---|
| `system.dashboard.view` | Access Platform Context | System Administration |
| `system.users.manage` | Manage all users | System Administration |
| `system.workspaces.manage` | Manage all workspaces (suspend/resume/license) | System Administration |
| `system.subscriptions.manage` | Manage subscriptions/revenue | Subscriptions |
| `system.plans.manage` | Manage plans & coupons | Subscriptions |
| `system.settings.manage` | Manage platform settings | System Administration |
| `system.ai.manage` | Manage global AI providers/models | AI Engine |
| `system.integrations.manage` | Manage global connectors | Integration Platform |
| `system.diagnostics.run` | Run diagnostics | Observability |
| `system.observability.view` | View platform metrics/logs/errors | Observability |
| `system.maintenance.manage` | Maintenance mode, cache, cleanup | Observability |
| `system.backups.manage` | Manage backups/restore | Observability |
| `system.audit.view` | View platform audit log | Audit |

---

## 3. Detailed Key Reference

For each key: **what it unlocks** in the Platform Context, its **dependencies**,
and its **denied behavior**. Dependencies follow `PERMISSION_CATALOG.md` Rule 1
(a composite action may require several keys) and the principle that any console
surface is reachable only when the holder can also enter the Platform Context.

### 3.1 `system.dashboard.view`

| Field | Detail |
|---|---|
| **Module** | System Administration |
| **What it unlocks** | Entry to the **Platform Context** and its landing dashboard: global tenant counts, platform health summary, and the System Administration navigation root. This is the gateway key for the platform console. |
| **Dependencies** | None. It is the **base** dependency for every other `system.*` surface — a `User` MUST hold `system.dashboard.view` to reach the console in which the other system permissions are exercised. |
| **Denied behavior** | A `User` without this key has **no Platform Context**: the context switcher offers no platform option and any direct route to a platform URL returns the standard deny response. The `User` is unaffected as an ordinary workspace member. |

### 3.2 `system.users.manage`

| Field | Detail |
|---|---|
| **Module** | System Administration |
| **What it unlocks** | Lifecycle control over **all `User` accounts platform-wide**: view any user, suspend/reactivate (`USER_MODEL.md` §5), reset/verify email state, force session revocation, and grant or revoke the System Owner capability (the `system.*` keys themselves) on other users. |
| **Dependencies** | `system.dashboard.view` (to reach the console). Granting `system.*` keys to another user is itself a `system.users.manage` action and **MUST** be audited (§5). |
| **Denied behavior** | Denied users cannot enumerate or mutate any account other than their own. Self-service profile/session management remains available to every `User` as a baseline capability (`PERMISSION_CATALOG.md` §1) and is **not** gated by this key. |

### 3.3 `system.workspaces.manage`

| Field | Detail |
|---|---|
| **Module** | System Administration |
| **What it unlocks** | Cross-tenant administration of **every workspace**: list all tenants, suspend/resume a workspace, adjust its license/entitlements, and perform platform-level remediation. This is the control-plane counterpart to per-tenant `workspace.*` keys. |
| **Dependencies** | `system.dashboard.view`. Licensing actions coordinate with the Licensing/Subscriptions layer; revenue-bearing changes additionally require the relevant `system.subscriptions.manage` / `system.plans.manage` key. |
| **Denied behavior** | Denied users cannot view or act on workspaces they are not a member of. **Holding this key does NOT grant any in-tenant `workspace.*` capability** — see §4. Tenant isolation (`WORKSPACE_MODEL.md` §3) is never weakened by this key; it governs the workspace *as an administrative object*, not its business data. |

### 3.4 `system.subscriptions.manage`

| Field | Detail |
|---|---|
| **Module** | Subscriptions |
| **What it unlocks** | Platform-wide management of **subscriptions and revenue**: view and adjust any tenant's subscription state, handle dunning/renewals, and read consolidated revenue reporting across tenants. |
| **Dependencies** | `system.dashboard.view`. Plan/coupon catalog changes require `system.plans.manage`; tenant suspension as an enforcement action coordinates with `system.workspaces.manage`. |
| **Denied behavior** | Denied users see no cross-tenant subscription or revenue data. Per-workspace billing (`billing.view`/`billing.manage`) remains a **workspace** concern and is unaffected by this system key. |

### 3.5 `system.plans.manage`

| Field | Detail |
|---|---|
| **Module** | Subscriptions |
| **What it unlocks** | Authoring the **global product catalog**: create/edit plans, feature entitlements, tiers, and **coupons** offered to all tenants. |
| **Dependencies** | `system.dashboard.view`. Applying a plan to a specific tenant is a `system.subscriptions.manage` action. |
| **Denied behavior** | Denied users cannot create or modify plans or coupons; the plan catalog is read-only or absent in their console. No workspace-level key can substitute. |

### 3.6 `system.settings.manage`

| Field | Detail |
|---|---|
| **Module** | System Administration |
| **What it unlocks** | Management of **platform-wide settings** — the global defaults that apply when a workspace setting is absent (`WORKSPACE_MODEL.md` §4): platform identity, default locale/timezone, security baselines, and other global configuration registry entries. |
| **Dependencies** | `system.dashboard.view`. |
| **Denied behavior** | Denied users cannot alter global defaults. This key never reaches **per-workspace** settings, which are governed by `workspace.settings` / `settings.update` inside each tenant. |

### 3.7 `system.ai.manage`

| Field | Detail |
|---|---|
| **Module** | AI Engine |
| **What it unlocks** | Administration of the **global AI provider layer**: register/configure platform AI providers and models, set global limits/policies, and curate the provider catalog that workspaces draw from (`MODULES.md` §4). |
| **Dependencies** | `system.dashboard.view`. |
| **Denied behavior** | Denied users cannot view or change global providers/models. Per-workspace AI configuration (`ai.configure`, `ai.keys.manage`, `ai.prompts.manage`) is a **workspace** concern and is not unlocked by this system key. |

### 3.8 `system.integrations.manage`

| Field | Detail |
|---|---|
| **Module** | Integration Platform |
| **What it unlocks** | Management of **global connectors** and platform-level integration configuration exposed through the Integration Platform (API Gateway, connectors, SSO/SCIM-ready surfaces). |
| **Dependencies** | `system.dashboard.view`. |
| **Denied behavior** | Denied users cannot register or configure global connectors. Per-workspace integrations (`integration.manage`, `apikey.manage`, `webhook.manage`) remain workspace-scoped and unaffected. |

### 3.9 `system.diagnostics.run`

| Field | Detail |
|---|---|
| **Module** | Observability |
| **What it unlocks** | Execution of **platform diagnostics**: health probes, connectivity checks, and self-test routines across modules (probes are read from all modules per `MODULES.md` §5). |
| **Dependencies** | `system.dashboard.view`. Often paired with `system.observability.view` to interpret results. |
| **Denied behavior** | Denied users cannot trigger diagnostic runs. Read-only metric viewing still requires `system.observability.view` separately. |

### 3.10 `system.observability.view`

| Field | Detail |
|---|---|
| **Module** | Observability |
| **What it unlocks** | Read access to **platform metrics, logs, and errors** — the operational telemetry of the whole deployment. |
| **Dependencies** | `system.dashboard.view`. |
| **Denied behavior** | Denied users see no platform telemetry. This is **distinct** from `audit.view` (a workspace activity log) and from `system.audit.view` (the platform audit log). |

### 3.11 `system.maintenance.manage`

| Field | Detail |
|---|---|
| **Module** | Observability |
| **What it unlocks** | Operational controls: enabling/disabling **maintenance mode**, clearing **caches**, and running platform **cleanup** tasks. |
| **Dependencies** | `system.dashboard.view`. Maintenance mode affects all tenants; the action **MUST** be audited (§5). |
| **Denied behavior** | Denied users cannot enter maintenance mode, flush caches, or run cleanup. |

### 3.12 `system.backups.manage`

| Field | Detail |
|---|---|
| **Module** | Observability |
| **What it unlocks** | Management of **backups and restore**: schedule/trigger backups and perform restores of platform data. |
| **Dependencies** | `system.dashboard.view`. Restores are high-impact and **MUST** be audited (§5). |
| **Denied behavior** | Denied users cannot create, schedule, or restore backups. |

### 3.13 `system.audit.view`

| Field | Detail |
|---|---|
| **Module** | Audit |
| **What it unlocks** | Read access to the **platform audit log** — the immutable record of platform-level actions (see `AUDIT_EVENTS.md`), including grants of `system.*` keys and high-impact operations. |
| **Dependencies** | `system.dashboard.view`. |
| **Denied behavior** | Denied users cannot read the platform audit trail. This is **distinct** from the per-workspace `audit.view` / `audit.export` keys, which expose only one tenant's activity. |

---

## 4. System Permissions Are Inert in Workspace Context

A foundational rule (`PERMISSION_CATALOG.md` Rule 3; `PERMISSION_MODEL.md` §6):

- `system.*` keys are **visible and meaningful only in the Platform Context**.
  Inside a **Workspace Context**, they are **invisible and inert** — the workspace
  navigation, role builder, and permission pickers **MUST NOT** display them, and
  no workspace action **MAY** be authorized by a `system.*` key.
- The converse also holds: workspace keys (`PERMISSION_CATALOG.md` §2) are
  meaningful only in a Workspace Context and never authorize a platform action.
- Therefore, **holding `system.workspaces.manage` does NOT grant any in-tenant
  capability** such as `job.create`, `member.invite`, or `billing.manage`. A
  System Owner who needs to act *inside* a specific workspace must do so as a
  **member of that workspace**, with the appropriate **workspace** permissions
  granted to their `Membership` (§5).

This separation preserves tenant isolation — "the single most important security
invariant of the system" (`WORKSPACE_MODEL.md` §3) — while still allowing
platform operators to administer tenants *as objects* (suspend, license, resume)
without silently reading tenant business data.

---

## 5. A System Owner Is Also a Normal User

Per `USER_MODEL.md` §1–§4, the System Owner capability is layered onto an
ordinary `User`. The same person, with the same login:

- **MAY** operate the platform in the Platform Context (using their `system.*`
  keys), **and**
- **MAY** simultaneously create or join workspaces and act as an ordinary member
  in each — owner, recruiter, interviewer, candidate, employee, etc. — exactly
  like any other `User` (`USER_MODEL.md` §4; baseline capabilities in
  `PERMISSION_CATALOG.md` §1).

Consequences:

1. A System Owner's permissions **inside** a workspace come **only** from that
   workspace's roles/direct grants on their `Membership` — never from their
   system status (§4).
2. Baseline capabilities (`workspace.create`, `workspace.join`, apply to a job,
   manage own profile/sessions) are available to a System Owner because they are
   available to **every** `User`, not because of `system.*`.
3. The same user holding both levels is expected and supported; it does **not**
   create a second account (`USER_MODEL.md` §7, invariant 1).

**Auditing:** per `PERMISSION_MODEL.md` §7 and `AUDIT_EVENTS.md`, permission- and
role-affecting actions are recorded (who/when/what changed). System-level actions
— granting/revoking `system.*` keys, suspending tenants/users, maintenance mode,
restores — **MUST** be written to the platform audit log readable via
`system.audit.view`.

---

## 6. Bootstrapping the First System Owner

The platform must have a first operator without any pre-existing operator to grant
the capability. This is resolved by the **Installer** (`MODULES.md` §2,
Foundation layer; `INSTALLER_ARCHITECTURE.md`):

- During **zero-touch browser installation**, the Installer creates the **first
  `User`**, who **becomes the first System Owner** by receiving the `system.*`
  capability directly (`USER_MODEL.md` §5).
- This is the **only** path by which `system.*` keys are minted without an
  existing System Owner. Every subsequent grant of `system.*` is performed by an
  existing System Owner via `system.users.manage` and is audited (§5).
- **All subsequent self-registered users are plain `User`s** with no system
  permissions (`USER_MODEL.md` §5); they receive only baseline capabilities until
  explicitly granted otherwise.

> **Deny-by-default reminder:** absence of a `system.*` key ⇒ denied
> (`PERMISSION_MODEL.md` §5). No `User` acquires platform power implicitly; it is
> either installer-seeded (once) or explicitly granted and audited thereafter.

---

### Related Documents

`PERMISSION_CATALOG.md` · `PERMISSION_MODEL.md` · `WORKSPACE_PERMISSIONS.md` ·
`USER_MODEL.md` · `WORKSPACE_MODEL.md` · `MODULES.md` · `ROLE_BUILDER.md` ·
`ACCESS_POLICIES.md` · `SECURITY_MATRIX.md` · `AUDIT_EVENTS.md`
