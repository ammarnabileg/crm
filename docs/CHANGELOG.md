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

### Added — Phase 14: SaaS Billing, Subscriptions & Licensing
- **Billing Platform** (`app/Modules/Billing`) — `BILLING_PLATFORM.md`:
  - **Plans as data** (`PlanCatalog` + `PlanService`): Free/Pro/Enterprise seeded
    on install (`platform.installed` event) and via `php bin/console.php db:seed`;
    each plan carries `features` (flags) and `limits` (caps, -1 = unlimited).
  - **Subscriptions** (`SubscriptionService` + `BillingService`): one row per
    workspace with a trial→active→past_due→grace→suspended/canceled lifecycle.
  - **SubscriptionLifecycle**: time-driven transitions (`tick($now)`, injectable
    clock) — trial conversion, renewal, dunning → suspension, scheduled cancel.
  - **Payment gateway** (`PaymentGateway` contract + built-in network-free
    `ManualPaymentGateway`; Stripe/Moyasar are deferred adapters).
  - **Invoices** (`InvoiceService`): immutable, paid on successful charge.
  - **Licensing** (`Entitlements`): feature flags + numeric limits from the plan;
    permissive when unsubscribed; suspended/canceled ⇒ not usable.
  - **Billing UI** (`/billing`): plan/status, trial/renewal dates, switch plan,
    scheduled cancel, invoices; CSRF-protected and audited.
- **Single dynamic sidebar is now subscription-aware**: `SidebarBuilder` gains an
  optional enabled-features argument; AI/Workflows/Developer are feature-gated.
  Decoupled via a new Core `EntitlementResolver` contract (permissive null
  default in Core; real resolver bound by Billing) so Navigation/Workspaces never
  depend on Billing internals.
- **Migration**: plans, subscriptions, invoices. Installer now emits
  `platform.installed`; `db:seed` console command added.
- Verified on live MySQL 8 (`BillingTest`, `SubscriptionLifecycleTest`,
  `SidebarBuilderTest`). Suite: **81 tests / 255 assertions**.

### Added — Phase 15: Observability, Diagnostics & Operations
- **Observability** (`app/Modules/Observability`) — `OBSERVABILITY.md`, in the
  Platform Context (System Owner), reusing existing Health/Logger/ErrorHandler/
  event-bus/Audit infrastructure:
  - **MetricsService**: platform snapshot computed from existing domain tables
    (tenancy, MRR, recruitment, AI, automation, integration, health) — resilient
    (missing table ⇒ 0).
  - **ErrorTracker**: persists captured errors to `error_events`, fed by a new
    `system.error` event the Core `ErrorHandler` now publishes (Core stays
    DB-agnostic; telemetry never masks the original error).
  - **MonitorService.tick($now)**: opens/auto-resolves `alerts` for suspended/
    past-due subscriptions, webhook failure spikes, and error spikes.
  - **BackupService**: records runs + writes a verifiable table/row-count
    **manifest** to storage (full `mysqldump`/snapshots deferred to ops).
  - **Ops dashboard**: `PlatformContext` + `PlatformShell` (the **same** single
    dynamic sidebar, `context='platform'`) with `/admin` (overview), `/admin/
    diagnostics`, `/admin/diagnostics/json`, and run-backup.
- **Core**: `ErrorHandler` publishes `system.error`; `DatabaseProbe` already
  registered. `PlatformContext`/`PlatformShell` added under Workspaces.
- **Migration**: error_events, alerts, backups.
- Verified on live MySQL 8 (`ObservabilityTest`) **and** an end-to-end HTTP run
  (System Owner → `/admin` + diagnostics JSON: DB/PHP/storage healthy + live
  metrics). Suite: **88 tests / 291 assertions**.

### Added — Phase 16: Release Engineering & Final Certification
- **Production-readiness auditor** (`bin/certify.php`): boots the real kernel and
  runs **39 checks** — modules/acyclic deps, security/config, migrations + core
  tables, the **user-model & tenancy invariants** (single User identity, System
  Owner as a flag, Memberships M:N, roles-as-data, per-workspace candidate
  profiles), RBAC/sidebar integrity, routing surface, engine + event-bus wiring,
  and health. **39/39 PASS**.
- **Whole-platform end-to-end test** (`ReleaseCertificationTest`): a candidate
  applies → the single `application.submitted` event fans out to the Workflow
  Engine (AI) **and** Webhooks on a billed workspace, fully observable.
- **Browser certification** (`tests/browser/certify.mjs`, headless Chromium via
  `playwright-core`): logs in as a System Owner and verifies the Platform Context
  sidebar (Overview/Workspaces/Users/Subscriptions/Diagnostics/…) with
  screenshots.
- **Load baseline** (`bin/loadtest.sh`): `/api/v1/ping` ≈ 549 req/s, `/health`
  ≈ 485 req/s (single-threaded dev server).
- **`RELEASE_CERTIFICATION.md`**: architecture-compliance matrix, phase ledger,
  security posture, production-readiness checklist, and deferrals.
- `SidebarBuilder::allPermissionKeys()` added for the audit; `node_modules`
  gitignored. **Suite: 89 tests / 303 assertions.**
- **🎯 All 16 phases complete and certified.**

### Added — Interviews (AI + human) & Candidate Score
- **Interviews** on the workspace-scoped Candidate Profile (`InterviewService`):
  schedule **AI** or **human** interviews against an application; AI interviews
  route through the **central AI Engine** (`ai_interview` capability) and store a
  transcript + advisory score + recommendation; humans submit evaluations that
  **override** any AI suggestion (human-in-the-loop).
  - **Candidate Score** = average of completed interview scores **in that
    workspace** (privacy-isolated — Microsoft ≠ Google).
  - `InterviewController` + routes: `/interviews`, schedule from a profile, AI
    run, human evaluate — gated by `interview.view/schedule/ai.run/evaluate`.
  - Surfaced on the candidate profile (interviews list, score badge, forms) and
    a workspace Interviews index.
- **Migration**: interviews (workspace-scoped, FK application/job/user). Prompt
  catalog gains `ai_interview` + `interview_questions`.
- Auditor extended to 40 checks (interviews table + route). Verified on live
  MySQL 8 (`InterviewTest`). **Suite: 94 tests / 325 assertions.**

### Added — Files & CVs (workspace-scoped attachments)
- **Files module** (`app/Modules/Files`): a shared attachment service.
  - `FileService`: validated uploads (10 MB cap, extension allowlist) stored
    **outside the web root** under `storage/files/{workspace}/`; metadata in a
    `files` table; polymorphic attach via `entity_type` + `entity_id`.
  - `FilesController`: `/files` index, `/files/upload`, **permission-gated,
    tenant-checked streamed download** (`/files/{id}/download`), and delete —
    a workspace can never read another's files (privacy isolation).
  - `Request` now captures uploads (`Request::file()`).
- **Candidate Profile** gains a **CVs & files** section (upload + list +
  download/delete); Recruitment depends on `Files` and reuses `FileService`.
- **Migration**: files. Auditor extended to 41 checks (files table + route).
- Verified on live MySQL 8 (`FileTest`). **Suite: 99 tests / 338 assertions.**

### Added — Candidate Timeline (per workspace)
- **`CandidateTimelineService`**: merges a candidate's events **within one
  workspace** — applications, stage moves, interviews, notes, files, offers —
  into a single chronological timeline (newest first). Pure aggregation (no new
  tables), strictly workspace-scoped (a company only sees its own interaction);
  files are read via `FileService`, never a cross-module table read.
- Surfaced as a **Timeline** card on the candidate profile.
- This completes the Candidate Profile contract: **CVs/files · Interviews
  (AI + human) · Notes · Score · Timeline** — all per-workspace.
- Verified on live MySQL 8 (`CandidateTimelineTest`). **Suite: 101 tests / 346
  assertions** · auditor **41/41**.

### Added — Notification Center
- **Notifications module** (`app/Modules/Notifications`): personal notifications
  scoped to **(workspace, user)** — a reactor on the event bus, so actor modules
  stay decoupled. On `application.submitted` the candidate is notified
  automatically.
  - `NotificationService`: notify / list / unread count / mark read / mark all.
  - `NotificationsController` + `/notifications` (list, mark-all, mark-one);
    sidebar gains **Notifications** (`notification.view`).
- **Migration**: notifications. Auditor extended to 42 checks (table + route).
- Verified on live MySQL 8 (`NotificationTest`). **Suite: 104 tests / 358
  assertions.**

### Added — Recruitment Reports & Analytics
- **`ReportService`** + **`/reports`**: the workspace hiring funnel (published
  jobs → applications → interviews → offers → hires), applications-by-status,
  interview average score, and AI usage — aggregated from existing data (no new
  tables), strictly workspace-scoped. CSV export gated by `report.export`.
  - Closes the previously-empty **Reports** sidebar item with a real dashboard.
- Auditor extended to 43 checks (`/reports` route). Verified on live MySQL 8
  (`ReportTest`). **Suite: 106 tests / 368 assertions.**

### Added — AI Candidate Assessment + Advanced Search
- **`AssessmentService`** + `candidate_assessments`: an **advisory** AI assessment
  produced through the central AI Engine (`assess_candidate` capability) when an
  AI interview completes — overall **fit score**, **recommendation band**
  (82+ strong / 68–81 suitable / 50–67 maybe / <50 not suitable), **11 weighted
  skills** (`SkillCatalog`) with confidence + evidence, **behaviour** (DISC, Big
  Five, growth, stress tolerance, leadership), **red flags** with severity, and
  strengths/gaps. The AI never decides — a human does.
  - The candidate profile shows an **AI Assessment** panel (the HR-decision
    summary): score + band, skill bars, behaviour, strengths/gaps, red flags.
  - **Advanced search** on the candidates list: filter by min score,
    recommendation band, and a specific skill ≥ threshold (`search()`).
- **Migration**: candidate_assessments. Auditor now requires the table.
- Verified on live MySQL 8 (`AssessmentTest`). **Suite: 112 tests / 405
  assertions.**

### Added — Job enrichment + decision workflow + Kanban board
- **Jobs** now carry **seniority** (intern→executive), **salary range** and
  **currency** (migration + create form + `JobService::create`).
- **Decision workflow** (`ApplicationStatus`): the 11-state hiring pipeline
  (applied → ai_screening → qualified/disqualified → tech/manager interview →
  final_review → offer → hired/rejected/withdrawn). `ApplicationService::setStatus`
  (human decision, validated) + `statusBoard()`.
- **Pipeline Kanban** at `/pipeline` (was empty): workspace-wide board grouped by
  decision status with an inline move; a **decision bar** on each application in
  the candidate profile. Gated by `pipeline.view` / `pipeline.manage`.
- Auditor extended to 44 checks (`/pipeline`). Verified on live MySQL 8
  (`PipelineStatusTest`). **Suite: 116 tests / 416 assertions.**

### Added — Talent Pool
- **Talent pools** (`TalentPoolService`): workspace-scoped saved candidate lists
  for future roles (recruitment spec #14). Create pools; save a candidate to one
  or more pools (from the candidate profile); a candidate may belong to many
  pools; per-pool member views. Permissions `talent.view` / `talent.manage`;
  sidebar gains **Talent Pool**.
- **Migration**: talent_pools, talent_pool_members. Auditor extended to 45 checks.
- Verified on live MySQL 8 (`TalentPoolTest`). **Suite: 119 tests / 424
  assertions.**

### Notes
- Repository reset to a clean slate before Phase 1 (previous placeholder README
  removed; recoverable from git history).

---

[Unreleased]: https://github.com/ammarnabileg/crm/tree/claude/vibrant-wright-vv65qw
