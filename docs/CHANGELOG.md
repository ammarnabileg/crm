# CHANGELOG — HaHireAI

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> During the pre-implementation phases (1–6) the "changes" are documentation
> artifacts. Code-bearing changes begin at Phase 7 (Core Kernel).

---

## [Unreleased]

### Added — Phase 1: Project Constitution & Architecture Foundation
- `PROJECT_CONSTITUTION.md` — the supreme reference (vision, principles,
  architecture/development rules, naming, folder & module standards, security,
  performance, documentation, testing, release policies, amendment process).
- Canon model docs: `DOMAIN_MODEL.md`, `USER_MODEL.md`, `WORKSPACE_MODEL.md`,
  `PERMISSION_MODEL.md`.
- Canon architecture docs: `ARCHITECTURE.md`, `MODULES.md`.
- Orientation & standards docs: `SYSTEM_OVERVIEW.md`, `DATABASE_GUIDE.md`,
  `APPLICATION_FLOW.md`, `AI_ENGINE.md`, `INSTALLATION.md`, `API_GUIDELINES.md`,
  `CODING_STANDARD.md`, `UI_GUIDELINES.md`, `SECURITY_GUIDE.md`,
  `TESTING_GUIDE.md`, `DEPLOYMENT_GUIDE.md`.
- `docs/README.md` — documentation index, reading order, and status board.
- Established the foundational decisions: native-PHP modular monolith, the
  single `User` identity + `System Owner`, the `Workspace` tenant boundary,
  permission-based authorization (no hard-coded roles), ULID primary keys, and
  AI as a central engine.

### Added — Phase 2: System Blueprint & Module Architecture
- `SYSTEM_BLUEPRINT.md` — the complete engineering map (layers, business
  domains, module boundaries, dependencies, communication rules, shared
  services, expansion strategy).
- `STATE_DIAGRAMS.md` — canonical state machines (Job, Application, Offer,
  Interview, Employee, Membership, Invitation, Subscription, Workspace,
  Workflow execution).
- `NAVIGATION_MAP.md`, `USER_JOURNEYS.md`, `PERMISSION_MATRIX.md`.
- `FEATURE_SPECIFICATIONS/` — 23 per-module specifications plus an index,
  each with Purpose/Scope/Inputs/Outputs/Dependencies/Permissions/Events/
  Data/Acceptance-Criteria.
- Resolved Recruitment as a single bounded context (not fragmented modules).

### Added — Phase 3: Database Architecture & Data Modeling
- `ENTITY_CATALOG.md` — the authoritative list of every entity (~79 tables),
  its tenancy scope (Global vs Workspace), owning module, and relationships.
- `DATABASE_ARCHITECTURE.md` — the data-model design (naming, ULID PKs, FK
  referential actions, constraints, tenancy, migration engine).
- `ER_DIAGRAM.md` (Mermaid, all entities), `RELATIONSHIP_MATRIX.md`,
  `INDEXING_GUIDE.md`.
- `AUDIT_POLICY.md`, `ARCHIVING_POLICY.md`, `VERSIONING_POLICY.md`.
- Confirmed: ULID-only keys (no `AUTO_INCREMENT`), no MySQL `ENUM`, mandatory
  `workspace_id` tenant guard, no duplicated/derived data, acyclic FK ownership.

### Added — Phase 4: Permission Matrix, Security Model & Access Control
- `PERMISSION_CATALOG.md` — the single authoritative registry of every
  permission key (workspace `resource.action` + platform `system.*`).
- `SYSTEM_PERMISSIONS.md`, `WORKSPACE_PERMISSIONS.md` — detailed scoped views.
- `ROLE_BUILDER.md` — roles as workspace data; zero reserved roles.
- `ACCESS_POLICIES.md` — per-action Required-Permission/Dependencies/Denied
  behaviour.
- `SECURITY_MATRIX.md` — every screen/action mapped to its permission & policy.
- `AUDIT_EVENTS.md` — the authoritative audited-events catalog.

### Changed — Phase 4
- Aligned `NAVIGATION_MAP.md` permission keys to the authoritative
  `PERMISSION_CATALOG.md` (e.g. `file.view`→`files.view`,
  `recruitment.view`→`job.view`, Platform Overview→`system.dashboard.view`),
  resolving the only cross-document key drift found in self-review.

### Added — Phase 5: Navigation, UX & Screen Architecture
- `NAVIGATION_ARCHITECTURE.md`, `SIDEBAR_MODEL.md` — the dynamic navigation
  engine and the single permission/subscription/module-driven sidebar.
- `SCREEN_CATALOG.md` — every screen (purpose, entry points, permissions,
  actions, the six mandatory states, acceptance criteria).
- `SCREEN_RELATIONSHIPS.md`, `USER_EXPERIENCE.md` — screen graph and UX
  philosophy (one coherent product, unified search & notifications).
- `LAYOUT_SYSTEM.md`, `PAGE_STANDARDS.md` — app shell, tokens, RTL/LTR, and the
  standard page anatomy + mandatory states.
- `DASHBOARD_GUIDE.md` — dashboards as command centers (platform-baseline
  widgets in Phase 9; recruitment widgets in Phase 10).

### Changed — Phase 5
- Normalized illustrative permission shorthands in `SCREEN_CATALOG.md` to
  catalog keys (`system.diagnostics.run`, `system.maintenance.manage`).

### Added — Phase 6: Project Structure, Build System & Installer
- `PROJECT_STRUCTURE.md`, `DIRECTORY_STANDARD.md` — the authoritative project
  layout and naming/placement rules.
- Kernel design: `BOOTSTRAP_FLOW.md`, `SERVICE_CONTAINER.md`, `ROUTING_GUIDE.md`,
  `CONFIGURATION_GUIDE.md`, `ERROR_HANDLING_GUIDE.md`, `HEALTH_CHECK_SYSTEM.md`.
- Installer design: `INSTALLER_ARCHITECTURE.md`, `INSTALLATION_FLOW.md`
  (zero-touch, browser-only, first System Owner).
- Build/ops: `BUILD_SYSTEM.md`, `UPDATE_POLICY.md`, `BACKUP_POLICY.md`.
- `adr/0001-project-structure.md`.

### Changed — Phase 6 (amendment)
- **Adopted the canonical project structure** (`app/{Core,Modules,Shared,…}`,
  `bootstrap/`, `routes/`) and reconciled `PROJECT_CONSTITUTION.md` §8 (→ v1.1.0)
  and `ARCHITECTURE.md` §3 (→ v1.1.0) to match. Module DDD internals unchanged.

### Added — Phase 7: Enterprise Core Kernel & Foundation (first code)
- `app/Core/*` — the bespoke native-PHP kernel: DI `Container` (autowiring,
  singleton/transient, `call()`), `Kernel` boot pipeline, `Config\Repository`
  + `Config\Environment`, `Routing` (Router/Route/Dispatcher: verbs, params,
  groups, named routes, 404/405), `Events\Dispatcher`, PSR-3 `Logging\Logger`,
  `Errors\ErrorHandler` (dev/prod), `Modules` (Module + acyclic ModuleRegistry),
  `Health` (HealthChecker + probes), `Providers` (ServiceProvider +
  CoreServiceProvider), `Http\Request`/`Response`, and `Contracts\*`.
- Project scaffolding: `composer.json` (PSR-4 `HaHireAI\` → `app/`),
  `bootstrap/app.php`, `public/index.php`, `routes/web.php`, `config/app.php`,
  `config/database.php`, `.env.example`, `.gitignore`, `storage/`.
- Tests: `phpunit.xml` + unit/feature suites (30 tests / 48 assertions) and a
  standalone `bin/smoke.php` acceptance runner (28 checks). Kernel boots,
  routes, and serves `/`, `/up`, `/health` with no modules.

### Added — Phase 8: Foundation (Installer + Database + Authentication + RBAC)
- **Database layer** (`app/Core/Database`): Connection, ConnectionManager,
  Schema Blueprint/Builder, bespoke MigrationRunner, base Repository with the
  workspace_id tenant guard; `app/Shared/Ulid`; `DatabaseServiceProvider`.
- **Foundation migrations** (`database/migrations`): 15 Identity & Access +
  platform tables; `bin/console.php` (migrate/rollback/status/db:wipe/health).
- **RBAC** (`app/Modules/Permissions`): PermissionCatalog + seeder, RoleService,
  Authorizer (deny-by-default, keys not roles).
- **Users/Auth** (`app/Modules/Users`, `…/Authentication`): Argon2id hashing,
  registration, System Owner, credential auth, session-backed AuthContext.
- **Workspaces/Memberships** (`app/Modules/Workspaces`, `…/Memberships`):
  workspace creation (owner direct grants, no reserved roles), invitations.
- **Navigation** (`app/Modules/Navigation`): single permission-driven SidebarBuilder.
- **Installer** (`app/Modules/Installer`): zero-touch orchestration + self-lock.
- **Browser UI**: View renderer, Session/CSRF, installer wizard, login/register,
  no-workspace + workspace-creation screens, dashboard; module loading via
  `config/modules.php`.
- Provisioned MySQL 8 for development; verified the full FINAL ACCEPTANCE
  scenario plus an end-to-end HTTP flow. Suite: 42 tests / 102 assertions.

### Added — Phase 9: Workspace Platform & Collaboration
- **Audit** (`app/Modules/Audit`): append-only AuditLogger + ActivityFeed +
  ActivityController (workspace timeline).
- **Memberships UI**: member directory, invite-by-email, invitation accept flow.
- **Permissions UI** (`Role Builder`): create roles and assign permissions by
  category.
- **Workspaces**: WorkspaceContext (per-request authorization), WorkspaceShell
  (layout), multi-workspace switching, dashboard switcher, and per-workspace
  Settings (name/timezone/locale/currency).
- **Search** (`app/Modules/Search`): unified workspace-scoped search.
- New modules registered via `config/modules.php`; sidebar gains Roles,
  Activity, Search; permission catalog gains `search.use`.
- Verified end-to-end (HTTP): role-build → invite → accept → recruiter sidebar
  subset → activity timeline. Suite: 46 tests / 115 assertions.
- _Remaining in Phase 9:_ file manager, notification center, branding.

### Added — Phase 10: Recruitment Platform
- **Recruitment** (`app/Modules/Recruitment`) as a single bounded context:
  - **Jobs**: create / publish / archive, with a public, tokenized job page.
  - **Public apply**: authenticated candidates apply from the public job page;
    `recruitment.application.submitted` is audited.
  - **Pipeline**: per-job stages, drag-to-stage moves with
    `application_stage_history`, bulk-safe stage management.
  - **Candidates**: workspace-scoped candidate profiles with notes and tags.
  - **Offers → hire**: create/send an offer; acceptance hires the candidate and
    creates an `employee` record — closing the hiring loop.
- **Migrations**: recruitment tables (jobs, pipeline_stages, candidate_profiles,
  applications, application_stage_history, tags, candidate_notes,
  candidate_profile_tags) and offers + employees.
- Sidebar gains Jobs, Candidates, Pipeline, Offers; all actions permission-gated
  by catalog keys. Verified end-to-end (HTTP) across the full hiring loop and
  per-workspace candidate isolation.

### Added — Phase 11: Enterprise AI Engine & Multi-Provider Intelligence
- **AI Engine** (`app/Modules/AiEngine`) — the central intelligence layer
  (`AI_ENGINE.md`): modules request a **capability**, never a provider.
  - `Contracts/AiProvider` + built-in network-free `EchoProvider`;
    `ProviderRegistry` for pluggable adapters.
  - `PromptEngine` — versioned, data-driven prompt templates (defaults for
    `summarize_candidate`, `generate_job_description`, `candidate_recommendation`).
  - `AiSettingsService` — per-workspace provider/model/fallback; API keys
    **encrypted at rest** (`Shared/Encrypter`, AES-256-GCM) with masked hints.
  - `AiEngine` — capability dispatch with automatic **fallback**, plus per-run
    usage/cost/latency recording to `ai_sessions`.
  - `AiController` + settings UI; sidebar gains **AI** (`ai.view`).
- **Migrations**: ai_settings, ai_keys, ai_sessions, prompt_templates.
- Verified against live MySQL 8: capability runs, provider fallback, encrypted
  key round-trip, and provider switching.

### Added — Phase 12: Enterprise Workflow Engine & Automation Platform
- **Workflow Engine** (`app/Modules/Workflow`) — the central, event-driven
  automation layer (`WORKFLOW_ENGINE.md`): actor modules **publish events**;
  the engine **reacts**. Feature modules never automate themselves.
  - `WorkflowService` — workflows stored as **data** (`{ steps: [...] }`),
    enabled/published/soft-delete aware, looked up per workspace + trigger.
  - `ActionExecutor` — action catalog (`log`, `audit`, `run_ai`); the `run_ai`
    action routes through the **central AI Engine**, never a provider.
  - `WorkflowEngine` — runs every eligible workflow for a trigger, recording an
    **execution** (`workflow_executions`) and per-step rows (`workflow_steps`)
    with skipped-condition and failure handling; synchronous but queue-ready.
  - `WorkflowModule` — registers the `application.submitted` listener at boot;
    `WorkflowController` + view list workflows/executions and create automations.
- **Recruitment** now publishes `application.submitted` on a successful apply
  (decoupled via the `EventDispatcher` — Recruitment is unaware of the engine).
- **Migration**: workflows, workflow_executions, workflow_steps.
- **Permissions**: `workflow.{view,create,update,delete,execute}`; sidebar gains
  **Workflows** (`workflow.view`).
- Verified against live MySQL 8 (`WorkflowEngineTest`): trigger→execution with
  AI step, trigger/enabled filtering, **per-workspace isolation**, conditional
  skips, and the full actor→event→reactor path through the real container.

### Added — Phase 13: Enterprise Integration Platform & API Gateway
- **Integration Platform** (`app/Modules/Integration`) — inbound API + outbound
  webhooks (`INTEGRATION_PLATFORM.md`):
  - **API tokens** (`ApiTokenService`): workspace-scoped Bearer tokens, stored as
    a **SHA-256 hash** (plaintext shown once), revocable and expirable.
  - **API Gateway** (`/api/v1`, `ApiController` + `ApiContext`): every request is
    authenticated, **rate-limited**, permission-gated (keys, not roles), and
    **workspace-scoped**, returning a consistent JSON envelope. Endpoints: `ping`,
    `me`, `jobs`, `jobs/{id}`.
  - **Rate limiting** (`RateLimiter`): DB-backed fixed window shared across
    processes; `X-RateLimit-*` headers + `429`/`Retry-After`.
  - **Outbound webhooks** (`WebhookService` + `WebhookDispatcher`): endpoints as
    workspace data; **HMAC-SHA256-signed** deliveries via a pluggable `HttpClient`
    (`CurlHttpClient`; fake in tests); per-attempt `webhook_deliveries` records.
    A reactor on the event bus — actor modules just publish events.
  - **Developer Portal** (`/integrations`): issue/revoke tokens, manage webhook
    endpoints, review deliveries; CSRF-protected and audited.
- **Migration**: api_tokens, rate_limits, webhook_endpoints, webhook_deliveries.
- **Permissions**: `integration.view`, `api.tokens.manage`, `webhook.manage`;
  sidebar gains **Developer**.
- **Deferred (designed):** OAuth2/SSO, turnkey connectors, inbound webhooks,
  OpenAPI — they add adapters on these primitives, not new architecture.
- Verified on live MySQL 8 (`IntegrationApiTest`, `WebhookTest`) **and** an
  end-to-end HTTP run: token auth, 401/403/404/429, workspace isolation, signed
  webhook delivery, and the event→webhook path through the real container.
  Suite: **71 tests / 219 assertions**.

### Notes
- Repository reset to a clean slate before Phase 1 (previous placeholder README
  removed; recoverable from git history).

---

[Unreleased]: https://github.com/ammarnabileg/crm/tree/claude/vibrant-wright-vv65qw
