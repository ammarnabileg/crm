# HaHireAI — Documentation

> **HaHireAI** is an AI-native, multi-tenant **enterprise recruitment SaaS**,
> built on **Native PHP 8.3+** as a **modular monolith** — engineered to the
> standard of Slack, Notion, GitHub, Linear, and the Stripe Dashboard.

This `/docs` directory is the **single source of truth** for the project.
`PROJECT_CONSTITUTION.md` is supreme: if anything here or in code conflicts with
it, the Constitution wins.

---

## Start Here (reading order)

1. **`PROJECT_CONSTITUTION.md`** — the binding law of the project.
2. **`SYSTEM_OVERVIEW.md`** — what the product is and how it's built (16 phases).
3. **`DOMAIN_MODEL.md`** — the ubiquitous language (entities & relationships).
4. **`USER_MODEL.md`**, **`WORKSPACE_MODEL.md`**, **`PERMISSION_MODEL.md`** — the
   three core models.
5. **`ARCHITECTURE.md`** + **`MODULES.md`** — how the system is structured.
6. Then the guides relevant to your task (database, security, UI, API, testing…).

## Canonical Decisions (quick reference)

| Decision | Summary | Authority |
|---|---|---|
| **Stack** | Native PHP 8.3+, MySQL 8+, Tailwind, Alpine.js (UI only), Vanilla JS, Composer. No framework. | Constitution §5 |
| **Architecture** | Modular monolith; layers Presentation→Application→Domain→Infrastructure→Persistence. | `ARCHITECTURE.md` |
| **Module comms** | Contracts, Events, Shared Services only. No internals/tables across modules. No cycles. | `ARCHITECTURE.md`, `MODULES.md` |
| **Identity** | One `User`. `System Owner` = a `User` with `system.*` permissions. Contexts ≠ accounts. | `USER_MODEL.md` |
| **Tenancy** | `Workspace` is the isolation boundary; every scoped row has `workspace_id`. | `WORKSPACE_MODEL.md` |
| **AuthZ** | Permission-based, deny-by-default. Roles are data; no hard-coded roles. | `PERMISSION_MODEL.md` |
| **IDs** | ULID `CHAR(26)` `id` on every table; app-generated; no `AUTO_INCREMENT`. | `DATABASE_GUIDE.md` |
| **AI** | Central engine; modules request capabilities, never call providers. | `AI_ENGINE.md` |
| **UI** | One dynamic sidebar from (user, workspace, permissions, subscription, modules). Bilingual AR/EN. | `UI_GUIDELINES.md` |

## Documentation Map

### Phase 1 — Constitution & Foundation ✅
| Document | Purpose | Status |
|---|---|---|
| `PROJECT_CONSTITUTION.md` | Supreme rules & policies | Adopted |
| `SYSTEM_OVERVIEW.md` | Product & build overview | Draft |
| `DOMAIN_MODEL.md` | Entities & ubiquitous language | Adopted (Canon) |
| `USER_MODEL.md` | The single user identity | Adopted (Canon) |
| `WORKSPACE_MODEL.md` | Tenant boundary | Adopted (Canon) |
| `PERMISSION_MODEL.md` | Permissions → Roles → Members | Adopted (Canon) |
| `ARCHITECTURE.md` | Modular-monolith layers & rules | Adopted (Canon) |
| `MODULES.md` | Canonical module map & deps | Adopted (Canon) |
| `DATABASE_GUIDE.md` | DB conventions & standards | Draft |
| `APPLICATION_FLOW.md` | Hiring lifecycle (conceptual) | Draft |
| `AI_ENGINE.md` | AI-as-engine overview | Draft |
| `INSTALLATION.md` | Install (zero-touch) & dev setup | Draft |
| `API_GUIDELINES.md` | Internal contracts + REST standards | Draft |
| `CODING_STANDARD.md` | PHP coding standard | Draft |
| `UI_GUIDELINES.md` | UI principles | Draft |
| `SECURITY_GUIDE.md` | Security by design | Draft |
| `TESTING_GUIDE.md` | Test strategy & gates | Draft |
| `DEPLOYMENT_GUIDE.md` | Build, deploy, release | Draft |
| `CHANGELOG.md` | Change history | Living |

### Planned (later phases)
- **Phase 2 — System Blueprint:** `SYSTEM_BLUEPRINT.md`, `NAVIGATION_MAP.md`,
  `USER_JOURNEYS.md`, `STATE_DIAGRAMS.md`, `PERMISSION_MATRIX.md`,
  `FEATURE_SPECIFICATIONS/`.
- **Phase 3 — Database Architecture:** `DATABASE_ARCHITECTURE.md`,
  `ER_DIAGRAM.md`, `ENTITY_CATALOG.md`, `RELATIONSHIP_MATRIX.md`,
  `INDEXING_GUIDE.md`, `AUDIT_POLICY.md`, `ARCHIVING_POLICY.md`,
  `VERSIONING_POLICY.md`.
- **Phase 4 — Access Control:** `PERMISSION_CATALOG.md`, `SECURITY_MATRIX.md`,
  `ROLE_BUILDER.md`, `ACCESS_POLICIES.md`, `SYSTEM_PERMISSIONS.md`,
  `WORKSPACE_PERMISSIONS.md`, `AUDIT_EVENTS.md`.
- **Phase 5 — Navigation & UX:** `NAVIGATION_ARCHITECTURE.md`,
  `SCREEN_CATALOG.md`, `USER_EXPERIENCE.md`, `LAYOUT_SYSTEM.md`,
  `SIDEBAR_MODEL.md`, `DASHBOARD_GUIDE.md`, `PAGE_STANDARDS.md`,
  `SCREEN_RELATIONSHIPS.md`.
- **Phase 6 — Structure & Installer:** `PROJECT_STRUCTURE.md`,
  `DIRECTORY_STANDARD.md`, `BOOTSTRAP_FLOW.md`, `SERVICE_CONTAINER.md`,
  `ROUTING_GUIDE.md`, `CONFIGURATION_GUIDE.md`, `INSTALLER_ARCHITECTURE.md`,
  `INSTALLATION_FLOW.md`, `HEALTH_CHECK_SYSTEM.md`, `BUILD_SYSTEM.md`,
  `UPDATE_POLICY.md`, `BACKUP_POLICY.md`, `ERROR_HANDLING_GUIDE.md`.
- **Phases 7–16 — Implementation:** Core Kernel, Installer/DB/Auth/RBAC,
  Workspace platform, Recruitment, AI Engine, Workflow Engine, Integration
  Platform, Billing/Subscriptions, Observability, and Release certification —
  each with its own documents and tests (see `MODULES.md`).

`/docs/adr/` holds Architecture Decision Records for significant decisions.

## Documentation Conventions

- Written in **English**; product UI is bilingual **AR/EN**.
- Every doc starts with **Status · Version · Last updated** and ends with
  **Related Documents**.
- Binding language uses RFC 2119 keywords (**MUST**, **SHOULD**, **MAY**).
- Docs are kept in sync with reality; a stale doc is a defect.
