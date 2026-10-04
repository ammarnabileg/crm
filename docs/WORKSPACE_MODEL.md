# WORKSPACE MODEL — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `DOMAIN_MODEL.md`.

---

## 1. What a Workspace Is

A **Workspace** is an **independent, isolated tenant space** — the boundary of
all business data and the unit of subscription, settings, members, and modules.

A Workspace **is not**:
- a **Company** (a workspace *may store* company information in its settings, but
  company is not a required entity),
- a **Role** (roles live *inside* a workspace),
- a **User** (users *join* workspaces).

A Workspace is the generic "place where work happens." Although HaHireAI's first
business capability is Recruitment, the workspace platform is **domain-agnostic**
and can host other business modules later (see `MODULES.md`).

## 2. What a Workspace Contains

| Area | Description |
|---|---|
| **Members** | `Membership` records linking users to this workspace. |
| **Roles** | Workspace-defined permission bundles. |
| **Permissions** | Effective permissions per member (via roles + direct grants). |
| **Jobs / Applications / Candidates / Pipeline / Interviews / Offers** | Recruitment data (Phase 10+). |
| **Files** | Workspace-scoped file storage. |
| **Reports / Saved Views** | Workspace analytics. |
| **Settings** | Independent configuration (see §4). |
| **Branding** | Logo, cover, colors, favicon, email branding. |
| **AI Configuration** | Providers, keys, models, prompts, limits (Phase 11). |
| **Subscription** | Plan, status, limits, invoices (Phase 14). |
| **Audit Log** | Workspace activity trail. |
| **Integrations** | Workspace-scoped API keys, webhooks, connectors (Phase 13). |

## 3. Tenancy & Isolation

- Every workspace-scoped record carries `workspace_id` and is **invisible** to
  every other workspace. This is the **single most important security
  invariant** of the system.
- Isolation is enforced at the data-access (repository) layer via a mandatory
  tenant guard; see `DATABASE_ARCHITECTURE.md` and `SECURITY_GUIDE.md`.
- Global data (users, plans, the permission catalog, global AI provider
  definitions, system settings) is explicitly enumerated in `ENTITY_CATALOG.md`;
  everything else is workspace-scoped.

## 4. Settings (per workspace, independent)

Each workspace owns its settings and **never** inherits another workspace's:
name, slug, brand, timezone, language, currency, date format, AI settings,
recruitment settings, security policy, notification preferences, storage policy.
System defaults apply only when a workspace setting is absent.

## 5. Membership

`Membership` is the link between a `User` and a `Workspace`. It carries:
status, assigned roles, direct permission grants (if any), invitation reference,
joined-at, and last-activity. A user **MAY** belong to **unlimited** workspaces;
a workspace **MAY** have unlimited members (subject to plan limits). Full detail:
`MEMBERSHIP_ENGINE.md` (Phase 9).

## 6. Lifecycle

```
Created → Active → Archived → Restored → Active
Active → (Soft) Deleted        [data retained, recoverable]
Active → Ownership Transferred  [owner membership reassigned]
```

- Creating a workspace also creates the **owner Membership**, **default
  settings**, and **default permissions** for the owner — but **no default
  roles** (the owner defines roles themselves; see `PERMISSION_MODEL.md`).
- Soft delete and archiving never hard-delete business data; see
  `ARCHIVING_POLICY.md`.

## 7. Multi-Workspace UX

A user switches between workspaces freely. The active workspace defines the
**Workspace Context**; the platform also has a **Platform Context** for System
Owners. Navigation is generated from the current context, permissions,
subscription, and enabled modules (see `NAVIGATION_ARCHITECTURE.md`,
`SIDEBAR_MODEL.md`). There is exactly **one** sidebar, generated dynamically —
never one sidebar per role.

## 8. Invariants

1. All business data belongs to exactly one workspace; cross-workspace access is
   forbidden.
2. A workspace defines its own roles; the system ships **no** reserved roles.
3. Company is optional data inside settings, never a mandatory entity.
4. Subscription, limits, and enabled modules are per workspace (see
   `SUBSCRIPTION_ENGINE.md`).
5. Adding a new module must not require changing the workspace model — only
   registering the module and its permissions.

---

### Related Documents
`DOMAIN_MODEL.md` · `USER_MODEL.md` · `PERMISSION_MODEL.md` ·
`MEMBERSHIP_ENGINE.md` · `WORKSPACE_SETTINGS.md` · `SUBSCRIPTION_ENGINE.md` ·
`NAVIGATION_ARCHITECTURE.md`
