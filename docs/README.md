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

### Phase 8 — Foundation (Installer + Database + Auth + RBAC) ✅ (code)
Implemented against a real **MySQL 8** database and verified end-to-end:
- **Database layer:** Connection/ConnectionManager, Schema Blueprint/Builder
  (ULID keys, FKs, indexes, no ENUM), bespoke migration engine, base Repository
  with the **tenant guard**; 15 foundation tables via 7 migrations.
- **RBAC:** permission catalog + seeder, `RoleService` (roles-as-data), and an
  `Authorizer` (deny-by-default; checks keys, not role names).
- **Auth:** Argon2id, registration, login, sessions; **Installer** (zero-touch).
- **Workspaces/Memberships:** creation (owner gets all perms by direct grant,
  no reserved roles), invitations, and the single **dynamic sidebar**.
- **Browser UI:** installer wizard, login/register, no-workspace screen,
  workspace creation, dashboard — server-rendered (Tailwind), CSRF-protected.
- Tests: **42 PHPUnit / 102 assertions**; full acceptance scenario + an HTTP
  flow verified via `php -S` + curl.

> **Running locally:** needs PHP 8.3+, MySQL 8, Composer. `composer install`,
> copy `.env.example`→`.env`, then open `/install` in the browser (or
> `php bin/console.php migrate`). See `INSTALLATION.md`.

### Phase 9 — Workspace Platform & Collaboration ✅ (code; files/notifications pending)
The daily collaboration platform, on the verified foundation:
- **Audit & Activity:** append-only `AuditLogger` + workspace `ActivityFeed`.
- **Members:** directory, invite-by-email, invitation accept flow.
- **Role Builder (UI):** create roles and pick permissions by category.
- **Multi-workspace:** `WorkspaceContext` (per-request authz), switching.
- **Settings:** per-workspace name/timezone/locale/currency (audited).
- **Search:** unified, workspace-scoped (members + roles).
- Modules registered via `config/modules.php`; the dynamic sidebar gains
  Roles, Activity, Search.
- Verified end-to-end (curl): owner builds a Recruiter role → invites a user →
  they accept → their sidebar is the recruiter subset → activity timeline shows
  every event. Suite: **46 tests / 115 assertions**.
- _Remaining in Phase 9:_ File manager, Notification center, workspace branding.

### Phase 10 — Recruitment Platform ✅ (code)
The recruitment bounded context, end-to-end on the verified foundation:
- **Jobs:** create / publish / archive, plus a public tokenized job page.
- **Public apply:** authenticated candidates apply; submission is audited.
- **Pipeline:** per-job stages, stage moves with full `*_stage_history`.
- **Candidates:** workspace-scoped profiles with notes and tags.
- **Offers → hire:** offer acceptance hires the candidate and creates an
  `employee` — closing the hiring loop.
- Sidebar gains Jobs, Candidates, Pipeline, Offers. Verified (curl) across the
  full hiring loop and per-workspace candidate isolation.

### Phase 11 — Enterprise AI Engine ✅ (code)
The central intelligence layer (`AI_ENGINE.md`) — modules request a
**capability**, never a provider:
- `AiProvider` contract + built-in network-free `EchoProvider`; pluggable
  `ProviderRegistry`.
- `PromptEngine` — versioned, data-driven templates (no hard-coded prompts).
- `AiSettingsService` — per-workspace provider/model/fallback; keys
  **encrypted at rest** with masked hints.
- `AiEngine` — capability dispatch with automatic **fallback** and per-run
  usage/cost/latency recording (`ai_sessions`).
- Sidebar gains **AI**. Verified against live MySQL 8 (runs, fallback, encrypted
  key round-trip, provider switching).

### Phase 12 — Enterprise Workflow Engine ✅ (code)
The central automation layer (`WORKFLOW_ENGINE.md`) — actors **publish events**,
the engine **reacts**; feature modules never automate themselves:
- **Workflows as data** (`WorkflowService`): `{ steps:[…] }`, enabled/published
  aware, looked up per workspace + trigger.
- **Action catalog** (`ActionExecutor`): `log`, `audit`, `run_ai` — AI routes
  through the central AI Engine, never a provider.
- **Engine** (`WorkflowEngine`): records an execution + per-step rows with
  conditional skips and failure handling; synchronous but queue-ready.
- Recruitment publishes `application.submitted` (decoupled via `EventDispatcher`).
- Permissions `workflow.*`; sidebar gains **Workflows**.
- Verified against live MySQL 8 (`WorkflowEngineTest`): trigger→execution with
  AI step, trigger/enabled filtering, **per-workspace isolation**, conditional
  skips, and the full actor→event→reactor path through the real container.
- Suite: **59 tests / 178 assertions**.

### Phase 13 — Enterprise Integration Platform & API Gateway ✅ (core)
Inbound API + outbound webhooks (`INTEGRATION_PLATFORM.md`):
- **API tokens:** workspace-scoped Bearer tokens, stored hashed, revocable.
- **API Gateway (`/api/v1`):** every request authenticated, **rate-limited**,
  permission-gated (keys not roles), and **workspace-scoped**, with a consistent
  JSON envelope (`ping`, `me`, `jobs`, `jobs/{id}`).
- **Outbound webhooks:** endpoints as data; **HMAC-signed** deliveries via a
  pluggable HttpClient; per-attempt delivery records. A reactor on the event bus.
- **Developer Portal (`/integrations`):** manage tokens, webhooks, and review
  deliveries; sidebar gains **Developer**.
- _Deferred (designed):_ OAuth2/SSO, turnkey connectors, inbound webhooks,
  OpenAPI — adapters on these primitives, not new architecture.
- Verified on live MySQL 8 + an end-to-end HTTP run. Suite: **71 tests / 219
  assertions**.

### Phase 14 — SaaS Billing, Subscriptions & Licensing ✅ (core)
SaaS monetization + licensing (`BILLING_PLATFORM.md`):
- **Plans as data** (Free/Pro/Enterprise) with `features` flags and `limits`.
- **Subscriptions** with a trial→active→past_due→grace→suspended/canceled
  lifecycle; **SubscriptionLifecycle.tick($now)** drives time-based transitions.
- **Payment gateway** contract + built-in network-free `ManualPaymentGateway`
  (Stripe/Moyasar deferred adapters); immutable **invoices**, paid on charge.
- **Licensing** (`Entitlements`): feature flags + limits; the **single dynamic
  sidebar is now subscription-aware** (decoupled via a Core `EntitlementResolver`
  contract). Permissive when unsubscribed.
- **Billing UI (`/billing`)** + `db:seed`/install seeding.
- Verified on live MySQL 8. Suite: **81 tests / 255 assertions**.

### Phase 15 — Observability, Diagnostics & Operations ✅ (core)
Platform-context operability (`OBSERVABILITY.md`), reusing existing Health/
Logger/ErrorHandler/event-bus/Audit infrastructure:
- **Metrics** snapshot from existing domain tables (tenancy/MRR/recruitment/AI/
  automation/integration/health), resilient to partial outages.
- **Error tracking** via a `system.error` event the Core `ErrorHandler` now
  publishes (Core stays DB-agnostic; persisted by an Observability reactor).
- **Monitors & alerts** (`MonitorService.tick($now)`): suspended/past-due subs,
  webhook-failure and error spikes; open + auto-resolve.
- **Backups**: recorded runs + verifiable manifest to storage.
- **Ops dashboard** via `PlatformContext` + `PlatformShell` — the **same** single
  dynamic sidebar in `platform` context: `/admin`, `/admin/diagnostics`,
  `/admin/diagnostics/json`.
- Verified on live MySQL 8 + an end-to-end System-Owner HTTP run. Suite:
  **88 tests / 291 assertions**.

### Phase 16 — Release Engineering & Final Certification ✅
- **Auditor** `php bin/certify.php` — 39 production-readiness checks (modules,
  security, migrations, **user-model/tenancy invariants**, RBAC/sidebar, routing,
  engines/event-bus, health): **39/39 PASS**.
- **Whole-platform e2e** (`ReleaseCertificationTest`): one event fans out to
  Workflow (AI) + Webhooks on a billed, observable workspace.
- **Browser certification** (headless Chromium) + **load baseline**
  (`bin/loadtest.sh`), and the **`RELEASE_CERTIFICATION.md`** report.
- **Suite: 89 tests / 303 assertions.** 🎯 **All 16 phases complete & certified.**

### Post-certification enhancements
- **Interviews (AI + human) + Candidate Score** on the workspace-scoped profile:
  schedule AI/human interviews, AI routes through the central AI Engine (advisory),
  human evaluations override, and the candidate Score = average of completed
  interview scores **per workspace** (privacy-isolated). `InterviewTest` green.
- **Files & CVs**: workspace-scoped attachments stored outside the web root, with
  permission-gated, tenant-checked streamed download (no cross-workspace access).
  Surfaced as **CVs & files** on the candidate profile. `FileTest` green.

### Verifying the build
```
php bin/console.php migrate && php bin/console.php db:seed
php bin/certify.php          # 41/41
vendor/bin/phpunit          # 99 tests
```

`/docs/adr/` holds Architecture Decision Records for significant decisions.

## Documentation Conventions

- Written in **English**; product UI is bilingual **AR/EN**.
- Every doc starts with **Status · Version · Last updated** and ends with
  **Related Documents**.
- Binding language uses RFC 2119 keywords (**MUST**, **SHOULD**, **MAY**).
- Docs are kept in sync with reality; a stale doc is a defect.
