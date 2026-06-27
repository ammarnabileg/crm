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

### Phase 2 — System Blueprint & Module Architecture ✅
| Document | Purpose | Status |
|---|---|---|
| `SYSTEM_BLUEPRINT.md` | Complete engineering map | Adopted (Canon) |
| `STATE_DIAGRAMS.md` | Canonical state machines | Adopted (Canon) |
| `NAVIGATION_MAP.md` | Screen-relationship map | Draft |
| `USER_JOURNEYS.md` | End-to-end journeys | Draft |
| `PERMISSION_MATRIX.md` | Permission × module matrix | Draft |
| `FEATURE_SPECIFICATIONS/` | 23 per-module specs (+ index) | Draft |

### Phase 3 — Database Architecture & Data Modeling ✅
| Document | Purpose | Status |
|---|---|---|
| `ENTITY_CATALOG.md` | Authoritative entity & tenancy list | Adopted (Canon) |
| `DATABASE_ARCHITECTURE.md` | Data-model design | Adopted |
| `ER_DIAGRAM.md` | Mermaid ER diagrams (all entities) | Adopted |
| `RELATIONSHIP_MATRIX.md` | 1:1 / 1:N / N:M per entity | Adopted |
| `INDEXING_GUIDE.md` | Per-table index strategy | Adopted |
| `AUDIT_POLICY.md` | What/how is audited | Adopted |
| `ARCHIVING_POLICY.md` | Archive / soft-delete / hard-delete | Adopted |
| `VERSIONING_POLICY.md` | Entity version history | Adopted |

### Phase 4 — Permission Matrix, Security Model & Access Control ✅
| Document | Purpose | Status |
|---|---|---|
| `PERMISSION_CATALOG.md` | Authoritative permission-key registry | Adopted (Canon) |
| `SYSTEM_PERMISSIONS.md` | `system.*` permissions detail | Adopted |
| `WORKSPACE_PERMISSIONS.md` | Workspace permissions detail | Adopted |
| `ROLE_BUILDER.md` | Roles-as-data (no reserved roles) | Adopted |
| `ACCESS_POLICIES.md` | Per-action policy table | Adopted |
| `SECURITY_MATRIX.md` | Screen/action × permission × policy | Adopted |
| `AUDIT_EVENTS.md` | Authoritative audited-events catalog | Adopted |

### Phase 5 — Navigation, UX & Screen Architecture ✅
| Document | Purpose | Status |
|---|---|---|
| `NAVIGATION_ARCHITECTURE.md` | Dynamic navigation engine | Adopted |
| `SIDEBAR_MODEL.md` | Single dynamic sidebar model | Adopted |
| `SCREEN_CATALOG.md` | Every screen (purpose/states/AC) | Adopted |
| `SCREEN_RELATIONSHIPS.md` | How screens connect | Adopted |
| `USER_EXPERIENCE.md` | UX philosophy & patterns | Adopted |
| `LAYOUT_SYSTEM.md` | App shell, grid, tokens, RTL/LTR | Adopted |
| `PAGE_STANDARDS.md` | Page anatomy & mandatory states | Adopted |
| `DASHBOARD_GUIDE.md` | Dashboards as command centers | Adopted |

### Phase 6 — Project Structure, Build System & Installer ✅
| Document | Purpose | Status |
|---|---|---|
| `PROJECT_STRUCTURE.md` | Authoritative project layout | Adopted (Canon) |
| `DIRECTORY_STANDARD.md` | Folder/file/namespace rules | Adopted (Canon) |
| `BOOTSTRAP_FLOW.md` | Request lifecycle from index.php | Adopted |
| `SERVICE_CONTAINER.md` | Bespoke DI container design | Adopted |
| `ROUTING_GUIDE.md` | Routing system design | Adopted |
| `CONFIGURATION_GUIDE.md` | Config/env loading | Adopted |
| `INSTALLER_ARCHITECTURE.md` | Zero-touch installer | Adopted |
| `INSTALLATION_FLOW.md` | Setup wizard steps | Adopted |
| `HEALTH_CHECK_SYSTEM.md` | Pluggable health probes | Adopted |
| `ERROR_HANDLING_GUIDE.md` | Global error handling | Adopted |
| `BUILD_SYSTEM.md` | Build lifecycle | Adopted |
| `UPDATE_POLICY.md` | Safe updates | Adopted |
| `BACKUP_POLICY.md` | Backup/restore/recovery | Adopted |

ADRs: `adr/0001-project-structure.md`.

### Phase 7 — Enterprise Core Kernel & Foundation ✅ (code)
The bespoke native-PHP Core Kernel is implemented and verified:
- `app/Core/` — Container (DI/autowiring), Kernel, Config + `.env` loaders,
  Router/Route/Dispatcher, Event Dispatcher, Logger, Error Handler, Module
  Registry, Health Checker (+ probes), Service Providers, Request/Response.
- `bootstrap/app.php`, `public/index.php`, `routes/web.php`, `config/`.
- Tests: `vendor/bin/phpunit` → **30 passing**; `php bin/smoke.php` → **28/28**.
  The kernel boots, routes, and reports health with **zero modules**.

### Planned (later phases)
- **Phases 8–16 — Implementation:** Installer/DB/Auth/RBAC, Workspace platform,
  Recruitment, AI Engine, Workflow Engine, Integration Platform,
  Billing/Subscriptions, Observability, and Release certification — each with
  its own documents and tests (see `MODULES.md`).

`/docs/adr/` holds Architecture Decision Records for significant decisions.

## Documentation Conventions

- Written in **English**; product UI is bilingual **AR/EN**.
- Every doc starts with **Status · Version · Last updated** and ends with
  **Related Documents**.
- Binding language uses RFC 2119 keywords (**MUST**, **SHOULD**, **MAY**).
- Docs are kept in sync with reality; a stale doc is a defect.
