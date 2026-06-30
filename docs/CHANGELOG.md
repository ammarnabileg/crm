# CHANGELOG — HaHireAI

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> During the pre-implementation phases (1–6) the "changes" are documentation
> artifacts. Code-bearing changes begin at Phase 7 (Core Kernel).

---

## [Unreleased]

### Added — Workspace Wallet Billing (v2)
- **Per-workspace prepaid wallet + composed monthly plan.** Billing now belongs to
  the Workspace (a company), not the user: each workspace owns a **wallet** (USD
  credits), pays **per billable seat** (every staff member beyond the free Owner)
  plus **flat-priced premium features**, with **basics (Jobs/Interviews) free**.
- **Fawaterak top-up** (hosted iframe + signed, idempotent webhook) as the only
  money-in path, behind the `PaymentGateway`/`HostedCheckoutGateway` contracts.
- **Plan composer** with a mandatory pre-activation review, **add-ons** that expire
  with the plan, **auto-renew from the wallet**, and a **locked** state that blocks
  staff (except `billing.manage`) from everything but the billing page.
- **Platform-set pricing catalog** (`system.pricing.manage`); new workspace billing
  permission keys; billing **domain events** for the Workflow product.
- Docs: `WALLET_AND_BILLING.md`, ADR `0002-workspace-wallet-billing.md`; updates to
  `BILLING_PLATFORM.md`, `PERMISSION_CATALOG.md`, `WORKFLOW_EVENTS.md`.

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

### Added — Interview invitation links
- **Tokenized interview links** (`InterviewInvitationService`, recruitment spec
  #5): generate a link from the job page; valid **14 days**, **single-use**. The
  public page (`/interview/{token}`, no login) shows the start page when valid,
  **"expired or invalid"** when lapsed/unknown, and **"interview completed
  successfully"** once used. Starting a link (when tied to an application) runs
  the AI interview + assessment, then consumes the link.
- **Migration**: interview_invitations. Auditor extended to 45 checks (table).
- Verified on live MySQL 8 (`InterviewInvitationTest`). **Suite: 123 tests / 431
  assertions.**

### Added — My Workspaces + AI interviewer Avatars
- **My Workspaces** (`/my-workspaces`): every workspace the current user belongs
  to (any role) with owner, members, plan, created, an **Enter** (switch) button,
  and totals (total / active / suspended). Sidebar gains **My Workspaces**.
- **Avatars** (`AvatarService`, recruitment spec #3): AI interviewer personas —
  name, persona, gender, language, image, style notes — workspace-scoped CRUD at
  `/avatars`. Permissions `avatar.view` / `avatar.manage`; sidebar gains
  **Avatars**.
- **Migration**: ai_avatars. Auditor extended to 50 checks (routes).
- Verified on live MySQL 8 (`AvatarTest`). **Suite: 125 tests / 448 assertions.**

### Added — Per-workspace AI analytics dashboard
- **`AiAnalyticsService`** + `/ai/analytics` (spec #16): AI performance and token
  consumption for the workspace — total runs, tokens, cost, average latency,
  failures, fallbacks; breakdowns **by capability** and **by provider**; recent
  runs. Linked from AI settings. Gated by `ai.view`.
- Auditor extended to 51 checks. Verified on live MySQL 8 (`AiAnalyticsTest`).
  **Suite: 126 tests / 456 assertions.**

### Added — Candidate interview feedback
- **`InterviewFeedbackService`** (spec #17): after an AI interview the candidate
  is invited (on the completion page) to rate the experience (1–5) + comment —
  one per interview, surfaced to the hiring team via a workspace summary.
- Public flow records feedback at `/interview/{token}/feedback`; the completion
  page shows the form until it's submitted.
- **Migration**: interview_feedback. Verified on live MySQL 8
  (`InterviewFeedbackTest`). **Suite: 128 tests / 462 assertions; auditor 51/51.**

### Added — Candidate comparison + AI Q&A
- **`ComparisonService`** + `/candidates/compare` (spec #15): select candidates
  from the list and view their AI assessments **side by side** (fit, band, 11
  skills, behaviour, strengths/gaps). Ask the AI a natural-language question
  across them ("who's best for a people-facing role?", "best English?") via the
  `compare_candidates` capability — advisory; the human decides. Workspace-isolated.
- Candidates list gains selection checkboxes + a **Compare selected** action.
- Auditor extended to 52 checks. Verified on live MySQL 8 (`ComparisonTest`).
  **Suite: 130 tests / 470 assertions.**

### Added — Human Interviews (panel stage + structured evaluation)
- **`HumanInterviewController`** + `/human-interviews` (spec #12): a dedicated
  second-stage page to **search / view / schedule / reschedule / archive** panel
  interviews (online with a meeting link, or onsite) and record the structured
  evaluation — six 1–5 dimensions (Technical depth, Problem solving,
  Communication, Culture fit, Takes ownership, Seniority fit) plus Strengths,
  Weaknesses, Overall (1–5 → 0–100 score), Recommendation and Notes. The detail
  ratings are persisted as JSON on the interview; AI stays advisory, the human wins.
- `InterviewService`: `listForWorkspace($ws, $type)` type filter, plus
  `schedulableApplications`, `findDetailed` (decodes `details`), `reschedule`,
  `archive`, and `meeting_link` on `schedule`. Migration adds
  `interviews.meeting_link` + `interviews.details` (JSON).
- Sidebar splits **AI Interviews** (`/interviews`, now AI-only) from **Human
  Interviews** (`/human-interviews`); both gated by `interview.view`.
- Auditor extended to 53 checks. Verified on live MySQL 8 (`HumanInterviewTest`).
  **Suite: 136 tests / 496 assertions.**

### Added — Candidate Portal & Workspace Chooser (the applicant side)
- **The candidate context** — the mirror of a membership. A User is a *candidate*
  in a Workspace when they have applied there and hold **no role**; a member with
  a role never sees the portal (constitutional: User is the origin, the workspace
  decides the menu by context, not by account type).
  - `CandidacyService` (workspaces-for-candidate excluding member workspaces,
    `isCandidate`, and the portal `overview`), `CandidateContext` (resolves the
    current candidate workspace, honoring the user's current workspace),
    `CandidateShell` (renders the portal with a context-driven, non-permission
    sidebar).
  - `SidebarBuilder` gains a **candidate** context: Candidate Portal / Available
    Jobs / My Applications / My Profile — shown to applicants, hidden from staff.
- **`CandidatePortalController`** + `/portal/*`:
  - **Candidate Portal** (`/portal`) — overview of application statuses, interview
    appointments, offers awaiting you, and the latest open jobs.
  - **Available Jobs** (`/portal/jobs`) — the workspace's open roles with one-click
    apply (already-applied roles are marked).
  - **My Applications** (`/portal/applications`) + **detail** — a stage map
    (progress through the 11 statuses), the AI's advisory notes, "next step",
    interviews, and offers. Accept / decline a company offer, or **propose a
    counter-offer** with an explanatory note (spec #3).
  - **My Profile** (`/portal/profile`) — edit personal data (name, phone, years of
    experience, target salary).
- **Workspace chooser** (`/workspaces/select`) — "Choose a workspace to enter":
  lists where you work (→ dashboard) and where you've applied (→ portal), plus
  **Create your workspace**. Login landing now routes members → dashboard,
  applicants → portal, everyone else → the chooser.
- **Decoupling**: new Core contract `CandidateDirectory` (null default in Core,
  real adapter bound by Recruitment) lets the Workspaces module list a user's
  candidate workspaces without depending on Recruitment — mirrors
  `EntitlementResolver` (ARCHITECTURE.md §4).
- Migration adds candidate personal data (`users.phone/years_experience/
  target_salary`) and counter-offers (`offers.note/proposed_by`).
- Auditor extended to 59 checks (portal routes + the directory contract). Verified
  on live MySQL 8 (`CandidatePortalTest`) and end-to-end over HTTP (login →
  portal → apply → accept → hired). **Suite: 146 tests / 522 assertions.**

### Added — Conversational AI Interview Room (candidate, text — mode A)
- **`InterviewRoomService`** — a turn-by-turn interview engine: the AI greets,
  explains the role, and asks one question at a time; the candidate answers; the
  room closes **automatically** after the question budget (12) or the time window
  (20 min), whichever comes first. Resumable — a candidate can leave and come back
  within the window. Questions come from the AI engine's role-specific set when a
  real provider returns usable ones, else a deterministic default bank. On close
  it persists the transcript, scores it, and runs the structured assessment
  (advisory — a human always decides). Backed by a new `interview_messages` table
  and `interviews.started_at`.
- **The room in the portal**: applying now schedules the AI screening interview and
  drops the candidate at `/portal/interview/{id}` — "start now or later". The room
  (`portal.interview`) is a chat UI with a live **question counter** and
  **countdown timer**, and a **completion screen** ("Interview completed
  successfully") when done. The application detail surfaces a Start/Continue CTA.
- **Voice (mode B)** is layered on the same engine as progressive enhancement
  (browser speech-to-text writes into the answer box; hidden when unsupported).
  **Live-avatar video (mode C)** mounts over the same flow where the workspace has
  a HeyGen key — the questions, budget, timing and scoring are identical.
- Auditor extended to 61 checks. Verified on live MySQL 8 (`InterviewRoomTest`)
  and end-to-end over HTTP (apply → room → 12 answers → completion → scored).
  **Suite: 151 tests / 539 assertions.**

### Added — Feature completeness sweep (jobs depth, offers, exports, settings, CV)
- **Job depth**: per-job **question bank** (spec #4, feeds the AI interview room
  in order) and **evaluation criteria / rubric** (spec #2) as workspace data;
  job **edit** and **archive** (spec #1). New `JobContentService`,
  `JobService::update/archive/listPublished`.
- **Offers (staff)**: a real **/offers** page (the sidebar item now resolves),
  with **send / decline / withdraw** transitions and a **printable offer letter**
  (browser "Save as PDF") via a new `layouts.print`. `OfferService` gains
  `withdraw`, `listForWorkspace`, `findDetailed`.
- **Reports**: a **printable report** (spec #18) alongside the existing CSV
  (Excel) export.
- **CV on apply** (candidate spec): applicants attach a CV (PDF/Word) when
  applying, and manage a **CV library** on their profile (via `FileService`).
- **Both apply paths converge on the room**: the public job page now schedules the
  AI screening interview and drops the (authenticated) applicant into the same
  conversational room as the in-portal flow.
- **Workspace Settings** expanded: company profile, branding, security, and
  **maintenance mode** — when on, `WorkspaceShell` pauses the workspace for
  everyone except admins (members with `settings.update`).
- Auditor extended to 64 checks (new tables `interview_messages`, `job_questions`,
  `job_criteria`, `offers`; new routes). **Suite: 155 tests / 550 assertions.**

### Sprint 1 — Architecture Refactoring (Contract-first module communication)
- Modules now consume shared services through **Core contracts**, not concrete
  classes (ARCHITECTURE.md §4):
  - **`UserDirectory`** — the single User identity's public surface; `UserRepository`
    implements it; Authentication + Recruitment depend on the contract (removes the
    cross-module **Infrastructure** import — the audit's BLOCKER).
  - **`AuditRecorder`** — the logging shared service; `AuditLogger` implements it;
    **22** importers rewired from the concrete logger to the contract; bound in `AuditModule`.
- **No business logic in controllers**: extracted raw SQL out of `CandidatesController`
  (→ `CandidateProfileService::listForWorkspace`) and `OffersController`
  (→ `OfferService::latestApplicationId`); both controllers no longer depend on `Connection`.
- Verified: **155 tests / 550 assertions** green · auditor **65/65** · full
  candidate↔staff journey re-run end-to-end (**13 PASS / 0 CRITICAL**).
- Remaining architecture debt (next architecture pass): contract-ize Memberships,
  Permissions (Authorizer), and Files (FileService) shared services.

### Sprint 2 — Workspace Lifecycle
- **`WorkspaceLifecycleService`**: archive / restore / suspend / resume /
  **transfer-ownership** (the new owner becomes a member with the full permission
  set; `owner_user_id` updated — invariant preserved).
- **System Owner** controls (platform): suspend / resume / archive / restore any
  workspace from `/admin/workspaces` (CSRF-guarded, audited).
- **Owner** controls (workspace): transfer ownership (by email) and archive own
  workspace from Settings → Danger Zone (owner-only gate + ownership validation).
- Workspace **activity timeline + audit log** already present (`/activity`).
- Verified: **159 tests / 563 assertions** green · auditor **69/69** ·
  `WorkspaceLifecycleTest` (4).

### Sprint 3 (Slice 1) — Executive Dashboard
- The Dashboard is now a real executive view (no placeholder data): KPIs
  (employees, jobs, open/closed, applicants, **needs-attention** = strong AI screen
  awaiting a human), **hiring funnel**, **pipeline summary**, **today’s
  interviews**, **recent activity**, **recent jobs**, **AI recommendations**,
  **workspace health** score + signals, **subscription status**, and **quick actions**.
- Computed by a Recruitment-owned **`DashboardService`** exposed via the new Core
  contract **`RecruitmentSnapshot`** (null default in Core), so the Workspaces
  dashboard renders real KPIs without reading Recruitment tables — consistent with
  the Sprint 1 architecture.
- Verified: **161 tests / 573 assertions** · auditor **69/69** · `DashboardServiceTest` (2).

### Sprint 3 (Slice 2) — Candidate Decision Center
- **Stage history**: every decision-status change is recorded to a new
  `application_status_history` table (from→to, who, when) at the source
  (`ApplicationService::setStatus`), and shown on the candidate Decision Center.
- **Structured candidate data** on the per-workspace profile (`candidate_profiles.details`):
  skills, languages, education, certifications, current/expected salary, availability,
  location — edited by the candidate in their portal **Profile**, displayed on the
  recruiter's **Decision Center** so the hiring decision is made on one screen.
- Verified: **163 tests / 579 assertions** · auditor **69/69** · `DecisionCenterTest` (2).

### Sprint 3 (Slice 3a) — Pipeline drag-&-drop + bulk move
- The pipeline board now supports **HTML5 drag-&-drop** (drag a candidate card to a
  stage column → posts the status change) and **bulk move** (select multiple cards
  via checkboxes, choose a target stage, move them together via the new
  `/pipeline/bulk-status` endpoint). The per-card stage `<select>` remains as a
  no-JS fallback. All recorded in stage history + audited.
- Verified: **163 tests / 579 assertions** · auditor **70/70**.

### Sprint 3 (Slice 3b) — Jobs depth
- Job **clone** (duplicates the job as a fresh draft, copying its question bank +
  criteria), and **search + status filters** on the Jobs list.
- Verified: **165 tests / 588 assertions** · auditor **70/70**.

### Sprint 3 (Slice 3c) — AI Interviews depth
- AI Interviews list gains **search + status filter** and an **Excel (CSV) export**.
- New AI interview **report** page (/interviews/{id}): full **transcript** (room
  conversation as chat bubbles, or the stored transcript), score, recommendation,
  provider, summary, and the advisory AI assessment.
- Verified: **165 tests** · auditor **72/72**.

### Sprint 3 (Slice 3d) — Talent Pool depth
- **Smart lists** (auto-computed segments for re-engagement): “Strong AI, not hired”,
  “Previously rejected”, “Interviewed, no offer” — each with a one-click **bulk add** to
  any pool (`/talent-pool/bulk-add`).
- Verified: **167 tests** · auditor **73/73** · `TalentPoolDepthTest` (2).

### Sprint 3 (Slice 3e) — Avatars depth
- AI interviewer avatars are no longer CRUD-only: they carry a **system prompt**,
  **greeting**, **voice**, **knowledge brief**, and an **active/inactive status**,
  with a **preview/test** screen showing how the avatar opens an interview.
- Verified: **168 tests** · auditor **74/74** · `AvatarDepthTest` (1).

### Sprint 3 (Slice 3f) — Members lifecycle + Roles depth
- **Members** are no longer a read-only directory: each row now shows **last
  login**, **last activity**, **joined**, and a status pill (active/suspended/
  invited), with per-member **Suspend / Reactivate / Remove** actions gated by
  `member.suspend` / `member.reactivate` / `member.remove`. The **owner** and
  **yourself** are protected — never suspendable/removable. The page lists only
  this workspace's members, never all platform users.
- `last_activity_at` is now meaningful: it stays null until the member actually
  works in the workspace, then is stamped (throttled to once/minute) on the
  request hot path via `WorkspaceContext`. New `MembershipService::setStatus`,
  `remove` (soft delete), `touchActivity`; enriched `membersForWorkspace`.
- **Roles** depth: each role shows its **permission count** and **how many
  members use it**, with **Clone** (copies the permission set under a unique
  "(copy)" name) and **Delete** actions. Deleting a role that is still in use is
  blocked — you must reassign its members first. Gated by `role.clone` /
  `role.delete`. New `RoleService::rolesForWorkspaceWithUsage`, `findRole`,
  `usageCount`.
- New `time_ago()` view helper for relative timestamps.
- All member/role mutations are audited (`memberships.member.status_changed`,
  `memberships.member.removed`, `permissions.role.cloned`,
  `permissions.role.deleted`).
- Verified: **170 tests** · auditor **79/79** · `MemberAndRoleDepthTest` (2).

### Sprint 3 (Slice 3g-i) — Workspace Settings depth (logo · SMTP · legal)
- **Logo upload**: the Branding section now accepts a workspace logo (PNG/JPG/
  WEBP/GIF, ≤10 MB) stored via the shared, tenant-isolated `FileService` and
  streamed inline through a permission-gated `GET /settings/logo`. Replacing or
  removing a logo deletes the previous file so no orphans accrue.
- **Email (SMTP)**: from-name/from-email, host, port, username, password and
  encryption per workspace. The SMTP password is a secret — never echoed back,
  only overwritten when a new value is typed.
- **Legal**: legal company name, terms URL, privacy URL, registered address.
- `FileService` now also accepts `webp`/`gif` (SVG remains disallowed — XSS risk
  when served inline).
- **Bug fix (latent)**: `workspace_settings.value` is a JSON column, but
  `WorkspacePreferences` wrote raw strings — so any non-numeric preference
  (hex colours, URLs, free text) would fail to save with "Invalid JSON text".
  Saving worked only because every pref written so far was `'1'`/`'0'`. The
  service now JSON-encodes on write and decodes on read (with a raw fallback for
  legacy values), so all string preferences round-trip safely.
- Verified: **173 tests** · auditor **80/80** · `SettingsDepthTest` (3).

### Sprint 3 (Slice 3g-ii) — Diagnostics infrastructure panels
- The Platform diagnostics screen gains a read-only **infrastructure** grid of 8
  panels, each reporting *observed* facts (nothing mocked) via a new
  `SystemDiagnostics` service:
  - **Database** — driver, MySQL version, schema, table count, on-disk size.
  - **Storage** — path, writable, disk free/total/used %, uploads size.
  - **Cache** — filesystem driver, cache/compiled-view sizes, OPcache state.
  - **Automation queue** — workflow executions total / in-flight / failed.
  - **Mail** — per-workspace SMTP model, workspaces configured, OpenSSL/PHP mail.
  - **SSL / Transport** — scheme, host, proxy (X-Forwarded-Proto), OpenSSL.
  - **Scheduler & workers** — on-demand model, last backup, last error event.
  - **Runtime** — PHP version, memory limit/usage/peak, load average, extensions.
- Each panel carries an ok/warn/down/info status (e.g. storage ≥90 % ⇒ warn,
  failed executions ⇒ warn, non-https ⇒ warn) so problems stand out at a glance.
- Verified: **177 tests** · auditor **81/81** · `SystemDiagnosticsTest` (4).

### Sprint 4 (Slice 4a) — Careers search & filters (candidate portal)
- The candidate **Available jobs** page gains search (title/location/keyword) and
  structured filters (employment type, seniority, location) with a live result
  count and a clear-filters action. Filter dropdowns are populated from the
  workspace's actual published roles (`JobService::publishedFacets`), so only
  meaningful options appear.
- Verified: `CareersSearchTest` (2); full suite green at Sprint 4 close.

### Sprint 4 (Slice 4b) — Withdraw application (candidate portal)
- Candidates can **withdraw** their own active application from the application
  detail page (with confirmation). Only the owning candidate may withdraw, and
  only while the application is still active — hired/rejected/already-withdrawn
  applications cannot be withdrawn. The transition is recorded in the status
  history (`ApplicationService::withdraw`, reusing `setStatus`) and audited.
- Verified: `WithdrawApplicationTest` (3); full suite green at Sprint 4 close.

### Sprint 4 (Slice 4c) — Notifications: categories, search & archive
- The Notification Center gains **All / Unread / Archived** tabs with live
  counts, a **category** filter (by notification type) and **search** across
  title and body. Each notification can be **archived** (and restored);
  archiving removes it from the active list and the unread count.
- New `archived_at` column (migration), and `NotificationService` gains
  filtered `forUser`, `counts`, `categories`, `archive`/`unarchive`.
- Verified: **184 tests** · auditor **84/84** · `NotificationsDepthTest` (2).

### Sprint 1 (continued) — Files as a contract-bound shared service
- Introduced `Core\Contracts\FileStorage` as the public surface of the shared
  file-attachment service. `FileService` now `implements FileStorage`, the
  contract is bound in `FilesModule`, and every cross-module consumer
  (Candidate Portal, Candidates, Candidate Timeline, Workspace Settings) now
  depends on the contract instead of reaching into `Files\Application\FileService`
  directly — closing the last cross-module internal dependency introduced for the
  logo feature (ARCHITECTURE.md §4: modules talk through Contracts, not internals).
- Verified: auditor **85/85** (adds a FileStorage contract-resolution check);
  full suite **184 tests / 707 assertions** green (pure decoupling — no behaviour
  change).

### Sprint 1 (continued) — Memberships & Permissions as contract-bound services
- Added `Core\Contracts\AccessControl` (the authorization surface) and
  `Core\Contracts\MemberDirectory` (the workspace-membership surface). `Authorizer`
  and `MembershipService` now implement them; both are bound in their modules.
- Rewired every cross-module consumer to depend on the contracts instead of the
  concrete classes: `WorkspaceContext`, `PlatformContext`, `WorkspaceCreator`,
  `WorkspaceLifecycleService`, `WorkspaceController`, `DashboardController`,
  `ApiContext`, `WorkspaceChooserController`. The Workspaces module no longer
  reaches into Permissions/Memberships internals — the per-request authorization
  context now speaks only to Core contracts (ARCHITECTURE.md §4).
- Together with `FileStorage`, this completes the contract-ization of the shared
  services flagged in the Sprint 1 architecture pass (`UserDirectory`,
  `AuditRecorder`, `CandidateDirectory`, `RecruitmentSnapshot`, `EntitlementResolver`,
  `FileStorage`, `AccessControl`, `MemberDirectory`).
- Verified: auditor **87/87** (adds AccessControl + MemberDirectory checks);
  full suite green (pure decoupling — no behaviour change).

### Decision Center — interview transcript on the candidate profile
- Each interview in the staff candidate profile now links to its **transcript /
  report** page (the AI transcript, score, recommendation and assessment), so the
  decision view reaches the full conversation in one click.
- Refreshed `docs/AUDIT_REPORT.md` with a remediation-progress section and the
  updated verified baseline (184 tests · 87/87 · 0 CRITICAL).

### Native Excel (.xlsx) export
- Added `Shared\XlsxWriter` — a dependency-free native `.xlsx` (OOXML) writer
  built on `ZipArchive` (no third-party library). Emits the minimal valid package
  with inline strings; safe numbers become numeric cells while ULIDs, leading-zero
  values and free text stay strings, and XML is escaped.
- The **AI interviews** and **recruitment report** exports now produce real
  `.xlsx` files (were CSV) — closing the "Excel export is CSV" gap.
- Verified: `XlsxWriterTest` (2); auditor **87/87**; full suite green.

### Decision Center — CV/résumé text parser
- Added `ResumeParser` — a deterministic, dependency-free extractor that pulls
  structured fields from CV text: email, phone, LinkedIn/GitHub/portfolio links,
  years of experience, and skills (word-boundary keyword matching, so "Java" is
  not matched inside "JavaScript").
- On the staff candidate profile, a **"Paste CV text to auto-fill"** action runs
  the parser and merges the result into the candidate's structured details
  **non-destructively** (existing values are kept), gated by `candidate.note`
  and audited.
- (PDF/DOCX byte-extraction needs external tooling not guaranteed offline, so the
  input is text — a `.txt`/paste — rather than a silent, unreliable PDF scrape.)
- Verified: `ResumeParserTest` (4); auditor **88/88**; full suite green.

### Feature 14 gap closure (A) — Roles edit + view holders
- A role can now be **edited** (rename + change its permission set) via a
  `GET /roles/{id}` detail page with an inline editor (`POST /roles/{id}/edit`),
  gated by `role.update`. `RoleService::updateRole` replaces the grant set in a
  transaction.
- The role page lists **the members who hold it** (`membersWithRole`), so "view
  role" answers "who has this role?" — not just a count.
- Verified: `RoleEditTest` (2); auditor **90/90**.

### Feature 14 gap closure (B) — platform Users directory actions
- The platform **Users** page (`/admin/users`, System Owner) now shows **Status**,
  **Last login** and **Actions** columns, supports **search** (name/email), and
  can **activate / deactivate** a user. System Owners and your own account are
  protected (never deactivatable). `PlatformAdminService` gains search,
  `findUser`, and `setUserStatus` (which refuses System Owners).
- Verified: `AdminUsersTest` (2); auditor **92/92**.

### Feature 14 gap closure (C) — Tasks + dashboard "My tasks"
- New **Tasks** module: a workspace task list (`/tasks`) with create, assign,
  priority, due date, search/filter (status · mine · text), complete/reopen and
  delete — gated by new `task.view` / `task.manage` permissions, audited.
- The **Dashboard** now has a **My tasks** card showing your open tasks
  (soonest-due first), via a new `Core\Contracts\TaskBoard` so the dashboard
  stays decoupled from the Tasks internals (ARCHITECTURE.md §4).
- Sidebar gains a **Tasks** item; the permission catalog gains a Tasks category
  (seeded automatically on migrate).
- Verified: `TaskServiceTest` (2); auditor **96/96**.

### Feature 14 gap closure (D + E) — Maintenance page & Settings fields
- **Maintenance** is now its own page (`/settings/maintenance`): current status,
  message, an **allow-list of IPs** that bypass maintenance, and explicit
  **enable/disable** actions. `WorkspaceShell` honours the allow-list so a
  listed IP keeps working while everyone else (except admins) sees the pause
  screen. Maintenance was moved out of the general Settings form (single source
  of truth — no duplicate toggle).
- **Workspace Settings** gains **date format**, **contact email/phone** and
  **logo text** fields.
- Verified: `MaintenanceAndSettingsExtrasTest` (2); auditor **99/99**.

### Voice interview — server-side speech-to-text (OpenAI Whisper)
- The AI interview room's **voice mode (B)** can now transcribe **server-side**
  via **OpenAI Whisper**, using **the workspace's own OpenAI key** — the same key
  the workspace admin adds for the AI provider. Keys are per-workspace and
  isolated: a key set in one workspace is never used by any other workspace the
  member belongs to or owns (verified by test).
- New `AiEngine\Contracts\SpeechToText` + `OpenAiSpeechToText` (Whisper) bound in
  the AI module; the network call sits behind an injectable transport so logic is
  testable and a missing key / offline call **degrades gracefully** (returns
  null) — the room then falls back to the browser's on-device recognition.
- New candidate endpoint `POST /portal/interview/{id}/transcribe` (CSRF-guarded,
  ownership-checked). The room gains a **🎤 Record (AI)** button that records audio
  and fills the answer box from the transcript.
- Verified: `OpenAiSpeechToTextTest` (4, incl. key-isolation); auditor **100/100**.

### Fix — route {param} names must match handler arguments
- The dispatcher binds route parameters to controller arguments **by name**, so a
  route like `/roles/{id}` calling `show(string $roleId)` failed to resolve and
  500'd at runtime. Found via HTTP/E2E verification (service tests + the
  route-registration check didn't exercise param binding).
- Fixed the 7 affected routes by aligning the path param to the argument:
  `/roles/{id}…` → `/roles/{roleId}…` (show/edit/clone/delete) and
  `/members/{id}/…` → `/members/{membershipId}/…` (suspend/activate/remove).
  The members and role clone/delete routes had been latently broken since they
  were added.
- Added an auditor guard that reflects over **every** route and asserts each
  `{param}` maps to an identically-named handler argument, so this class of bug
  can never ship silently again. Auditor **101/101**.

### AI feature gating — keys drive what's on (per-workspace, isolated)
- New `AiEngine\Contracts\AiCapabilities`: AI features are usable only when the
  workspace has the right key. **OpenAI key → AI (text/voice) interviews + AI CV
  analysis**; **HeyGen key (+ OpenAI) → live video avatar**. Keys are
  per-workspace and isolated (a key in one workspace never enables another).
- **Without the key the feature is off, not silently faked**: applying to a job no
  longer schedules an AI interview when there's no OpenAI key (the application
  still proceeds for the team to handle); staff "Run AI interview", the public
  interview link, and AI candidate summaries all no-op with a clear message.
- **AI settings** shows each feature's Active/Inactive status; the video toggle is
  disabled without its keys and a **pop-up** explains why if you try to force it.
- **Seamless enable**: adding an OpenAI key activates interviews + CV analysis;
  adding a HeyGen key auto-enables video — no extra toggling.
- **ATS works without AI**: jobs, applications, CV upload, human-interview
  scheduling all run regardless. Added a non-AI **CV keyword search** over
  candidate name/email + CV-derived data (`searchByKeyword`), separate from the
  AI-assessment search.
- Workspace Settings gains an **Enable AI** button that links to AI settings.
- Verified: `AiCapabilityTest` (2, incl. isolation), `CandidateKeywordSearchTest`
  (1); auditor **103/103**.

### Platform governance — account plans & workspace caps (foundation)
- New account-level governance (System Owner): each account (owner user) has an
  `account_plans` record — a plan that caps how many workspaces it may run
  **active** at once, a renewal date, and granted **bonus months**. Plus a
  per-account `users.can_create_workspaces` block flag.
- `AccountPlanService` (cap from the plan's `limits.workspaces`, active-workspace
  count, expiry/usability, assign plan, grant months, block check) exposed to the
  Workspaces module through the new `Core\Contracts\WorkspaceAllowance` (§4).
- **Enforced at creation**: `POST /workspaces` now refuses when the account is
  blocked, its plan is expired/suspended, or it's already at its workspace cap —
  with a clear message.
- `PlatformSettings` service (support contact via the global settings store).
- Verified: `AccountPlanTest` (3); auditor **104/104**.

### Platform governance — plan management (System Owner)
- New **Plans** screen (`/admin/plans`): create / edit / delete subscription
  plans, each with a **max-workspaces** cap, price, interval, trial days, feature
  flags (AI/automation/integrations) and visibility. `PlanService` gains
  `allPlans`, `create`, `update`, `delete` (refused while a plan is in use) and
  `usageCount`; the cap lives in the plan's `limits.workspaces`.
- Verified: `PlanCrudTest` (2); auditor **108/108**.

### Platform governance — enforcement, suspended screen & support contact (System Owner)
- **Suspended workspaces**: the workspace shell now shows a 503 "Service paused"
  screen (with a contact-support button) when the workspace was stopped by the
  System Owner *or* its owner's account plan lapsed/was suspended (non-renewal).
  Driven by `WorkspaceAllowance::isUsable()` and the workspace status.
- **Support contact**: new **Platform settings** screen (`/admin/settings`) for
  the platform name and the support contact (email / phone / URL / message) shown
  on suspended workspaces. Exposed to other modules via the new
  `Core\Contracts\SupportInfo` (§4), implemented by `PlatformSettings`.
- **Account governance on the Users screen** (`/admin/users`): per-account
  **block/allow workspace creation**, **assign plan**, and **grant free months**,
  with each account's plan, active-workspace count vs. cap, renewal date and
  creation state surfaced inline. System Owners and the acting user are protected
  from these controls.
- Verified: `GovernanceEnforcementTest` (3); auditor **114/114**.

### Platform governance — owner activate/deactivate within the plan cap
- The account owner now controls **which** of their workspaces run, never more
  than the plan allows. On **My Workspaces** each owned workspace shows its state
  (active / paused) with **Deactivate** / **Activate** controls and an
  "owned running / plan" meter; Activate is blocked once the cap is reached.
  Routes `POST /workspaces/{id}/deactivate|activate` (owner-only, CSRF, audited).
- **Downgrade enforcement**: assigning a smaller plan auto-pauses the excess —
  the oldest `cap` workspaces keep running, the newest are archived; the owner
  re-activates whichever they want within the cap (`AccountPlanService::
  enforceActiveCap`). New `WorkspaceAllowance::canActivateWorkspace` mirrors the
  creation check minus the block flag (it governs concurrency, not new builds).
- Paused workspaces are non-operational: the shell serves the 503 "Service
  paused" screen for owner-paused (`archived`), platform-stopped (`suspended`)
  and non-renewed (plan) states, each with a tailored message. Account-level
  pages (My Workspaces) bypass the gate so the owner can always recover.
- Verified: `AccountPlanTest` (+2 → 5); auditor **116/116**.

### Platform governance — AI Providers oversight (System Owner)
- New **AI Providers** screen (`/admin/ai`) — fixes the only dangling owner-panel
  nav link. Read-only, platform-wide oversight: AI adoption (how many workspaces
  have keys), usage by provider (runs / tokens / spend), and a per-workspace
  table of default provider/model, configured keys and run count.
- **Key isolation preserved**: keys are added per workspace and stay private —
  the overview surfaces only the masked `key_hint`, never the encrypted key
  (`PlatformAdminService::aiOverview`).
- Verified: `PlatformAdminTest` (+1, asserts the encrypted key never leaks);
  a sidebar-vs-routes audit confirms **zero** dangling nav links; auditor
  **117/117**.

### Images are uploaded, never pasted as URLs
- The AI-avatar image is now a **file upload** (PNG/JPG/WEBP/GIF) instead of an
  "Image URL" text field — the last image-as-URL input in the app. Uploads are
  stored via the Files service (linked by `entity_type = avatar_image`) and
  streamed inline from `GET /avatars/{id}/image`; replacing one drops the old
  file so no orphans accrue. (The workspace logo was already an upload.)
- Verified: smoke (controller resolves with FileStorage, route registered, form
  uses multipart + file input, preview streams the upload); auditor route guard.

### Platform payment switch + free plans
- The System Owner can **turn payments on/off** for the whole platform from
  Platform settings (`/admin/settings`). The switch is exposed to Billing via the
  new `Core\Contracts\PaymentSettings` (§4), implemented by `PlatformSettings`.
- **Payments off → free mode**: `BillingService` now charges only when a real
  gateway is wired AND payments are on (`chargingEnabled()`/`freeMode()`).
  Otherwise every plan is granted free for a limited period — nothing is charged
  or suspended. Billing shows plans as **“Free for a limited time”** (original
  price struck through) and any billing-capable member can activate/switch plans
  for free.
- **Self-service account plan** (`/account/plan`): a new user-facing page where an
  account picks the plan that sets its **workspace capacity**. In free mode any
  account can switch to any plan at no cost (downgrades auto-pause the excess via
  the cap rules); in paid mode changes are arranged with support. Linked from My
  Workspaces and the sidebar (Plan).
- Verified: `BillingTest` (+1: a connected gateway with payments switched off
  still enters free mode and never charges; 9 total); smoke (toggle round-trips,
  both pages render in free/paid modes); certify route + contract guards.

### Payment charge reports (System Owner)
- New `payment_attempts` table records **every** charge against a member account
  — successes and failures — with provider, amount, reference, and (on failure)
  the gateway error code + message. `BillingService` logs an attempt on each
  charge via the new `PaymentAttemptService`.
- New **Payments** report (`/admin/payments`): success/failure stats, a
  status filter, and a row per charge. **Each failed charge shows the exact
  cause and how to fix it** — `PaymentDiagnostics` maps the gateway error code to
  a plain-language cause and a concrete remedy (declines, insufficient funds,
  expired card, 3-D Secure, bad API key, gateway unreachable, …).
- `PaymentResult` now carries an optional machine `code` so adapters can report
  structured failures.
- Verified: `BillingTest` (+1: a failing gateway records a `failed` attempt with
  the code, a working one records `success` with a reference, stats add up; 10
  total); smoke (report renders cause/remedy); certify route guard; auditor
  **122/122**.

### Unified design system + workspace switcher
- Reproducible Tailwind build (`tailwind.config.js` + `resources/css/app.css` +
  `npm run build:css`). **Light mode is the base** (`color-scheme: light`), one
  font identity (**Inter** + system fallback), and one brand accent — the
  app-wide `indigo-*` utilities map at the config level to the brand's azure
  blue, so the whole product matches the reference with no per-view churn.
- Reworked the authenticated shell to the reference layout: logo mark, an
  icon-per-item sidebar with an active-state pill, and a user avatar. The guest
  shell carries the same logo, font and palette.
- The top bar hosts a **workspace switcher** (no-JS `<details>` dropdown): the
  current workspace + chevron, opening to the user's workspaces (current
  highlighted) plus **New workspace**; switching posts to the existing
  `/workspaces/{id}/switch`. `WorkspaceContext::workspaces()` exposes the list it
  already loads (no extra query).
- Verified: render smokes (shell 9/9, switcher 8/8); CSS rebuild includes every
  new utility; visual review via headless-Chromium screenshots of login,
  dashboard, billing, payments, my-workspaces, users and the open switcher.

### Header notifications bell + user menu + edit profile
- **Notifications moved out of the sidebar into a header bell** (no-JS `<details>`):
  an unread badge, a dropdown with the recent notifications (unread dotted),
  **Mark all read** (posts to `/notifications/read`) and **View all**
  (`/notifications`). Exposed to the shell via the new
  `Core\Contracts\NotificationFeed` (§4), implemented by `NotificationService`;
  `WorkspaceContext` already loads what it needs (no extra query beyond the feed).
- **The header name + Sign-out collapsed into an avatar menu**: clicking the
  avatar opens name + email, **Edit profile**, and **Sign out**.
- New **Edit profile** page (`/account/profile`): update display name, email
  (kept unique) and password (current-password check + confirm).
  `UserRepository` gains `updateName/updateEmail/updatePassword`.
- Verified: smoke (bell + menu + edit-profile render, empty state, no sidebar
  Notifications) 8/8; visual screenshots of the bell, user menu, owner panel and
  profile; certify **125/125**.

### Platform Roles & Permissions (granular site managers)
- New **Roles & Permissions** tab in the owner panel (`/admin/roles`): the System
  Owner builds platform roles from the `system.*` permission catalog and assigns
  them to users, creating **granular "site managers"** instead of the
  all-or-nothing `is_system_owner` flag. Create / edit / delete roles, tick the
  exact permissions, and assign/unassign users — each with its holders shown.
- New `system.roles.manage` permission, `platform_roles` / `platform_role_permissions`
  / `platform_role_user` tables, and `PlatformRoleService` (Permissions module).
- `Authorizer::systemPermissionsForUser` now returns every `system.*` for a
  System Owner **or** the union of a user's platform-role permissions; and
  `PlatformContext` grants scoped platform access to anyone holding at least one
  platform permission (not just owners) — so a site manager sees only the tabs
  they're entitled to.
- Supporting page: the owner **Users** directory now shows each user's platform
  roles as badges, so you can see who the site managers are at a glance.
- Verified: `PlatformRoleTest` (2 — scoped grant/revoke, owner-full, update/delete
  resync); smoke (assign → Authorizer union → scoped `PlatformContext` access)
  15/15; certify **131/131**.

### Unified URLs — platform panel drops the `/admin` prefix
- The System-Owner panel moves off `/admin/*` to clean, unprefixed URLs so the
  product is one unified surface (no separate "admin area"): `/overview`,
  `/all-workspaces`, `/users`, `/permissions`, `/subscriptions`, `/plans`,
  `/payments`, `/ai-providers`, `/audit-log`, `/diagnostics`,
  `/platform-settings` (+ their POST actions). Collisions with company pages are
  avoided by distinct names (platform `/users` vs company `/members`/`/candidates`;
  platform `/permissions` vs company `/roles`; `/ai-providers` vs `/ai`;
  `/platform-settings` vs `/settings`).
- Routes, sidebar, redirects, form actions and the auditor route guard all
  updated. Verified: certify 131/131; full suite green.

### Platform shell wears a red accent
- The whole app's accent (`indigo-*` utilities) now resolves through CSS
  variables (`--brand-*`) instead of hardcoded hex. `:root` defines them as the
  azure-blue tenant identity; the System-Owner shell adds `.theme-platform` to
  `<body>`, which overrides the same variables to **red** — so the HaHireAI
  platform pages are unmistakable from the blue workspaces. Every accent utility
  (bg/text/border/ring/hover/focus, all shades and opacity modifiers) switches
  with zero per-view churn.
- `tailwind.config.js` (indigo → `rgb(var(--brand-N) / <alpha-value>)`),
  `resources/css/app.css` (blue on `:root`, red on `.theme-platform`),
  `PlatformShell` passes `platformTheme = true`, `layouts/app.php` toggles the
  body class. Verified: certify 131/131; full suite green; screenshots confirm
  red platform vs blue workspace.

### Public company careers page — `/view/{slug}`
- Every workspace now has a public, no-login **careers page** at
  `domain.com/view/{slug}`: the company's brand (logo, tagline, accent colour),
  "about", industry/website/contact, and a searchable, filterable list of its
  **published** jobs. Each opening links to the existing public job page where a
  visitor applies and opens a linked account — one converging apply flow.
- New Core contract `CompanyDirectory` (resolves an **active** workspace's public
  profile by slug; archived/suspended/deleted workspaces are not public),
  implemented by the Workspaces module's `CompanyDirectoryService` (reuses
  `WorkspacePreferences`); `PublicCareersController` (Recruitment) reuses
  `JobService::listPublished`/`publishedFacets` and streams the logo publicly via
  the `FileStorage` contract. New `layouts/public.php` (full-width, "Powered by
  HaHireAI" footer) and `recruitment/careers.php` views.
- Workspace **Settings → General** now shows a "View public careers page" link.
- Verified: `CompanyDirectoryTest` (4 — active profile, empty defaults, unknown
  slug, inactive/deleted not public); certify **134/134** (+2 routes, +1
  contract); full suite green.

### Unified URLs — the candidate experience drops the `/portal` prefix
- The applicant surface moves off `/portal/*` to clean, unprefixed URLs so a
  candidate is just another **context** on the one unified product (no separate
  "portal"): `/open-jobs` (+`/{id}/apply`), `/my-applications`
  (+`/{id}`, `/{id}/withdraw`, `/{id}/counter-offer`),
  `/my-offers/{id}/accept|decline`, `/my-profile` (+`/cv`),
  `/interview/{id}` (the logged-in interview room, +`/answer`,`/transcribe`),
  and `/candidacy/{workspaceId}/switch`.
- The public, emailed interview link is renamed `/interview/{token}` →
  `/interview-link/{token}` (+`/start`,`/feedback`) so the logged-in room can own
  the clean `/interview/{id}`.
- **Context-aware `/dashboard`**: a new session `context_type`
  (staff | candidate | platform) is set when you switch workspaces or apply.
  `/dashboard` now lands you correctly — staff see the staff dashboard;
  candidates are taken to their candidate home, which merges with the list at
  `/my-applications`. `AuthContext` gains `contextType()/setContextType()`;
  staff/candidate switches and the public apply flow set it.
- All redirects, the candidate sidebar, the workspace chooser, the job
  interview-link builder, and views updated; the standalone candidate home view
  was folded into `/my-applications`. Verified: certify **137/137**; candidate &
  staff HTTP smoke (new URLs 200, `/portal/*` 404, `/dashboard`→`/my-applications`
  for candidates, staff dashboard intact); full suite green.

### Unified context switcher + in-place navigation
- One switcher in the top bar moves a user between **everything they can be** on
  the single surface: 👑 **HaHireAI** (the platform panel, shown first for anyone
  with platform access) → the **Workspaces** they staff → the workspaces they're
  **Applying to** (candidate) → **New workspace**. One identity, many contexts.
- New `ContextSwitcher` (Navigation) builds the model from the Core contracts
  (`MemberDirectory`, `CandidateDirectory`, `AccessControl`) — decoupled from the
  owning modules. All three shells (`WorkspaceShell`, `CandidateShell`,
  `PlatformShell`) feed it to the layout; the candidate shell also gains the
  header notifications bell.
- **In-place navigation (pjax)**: clicking a context (or a sidebar item) swaps
  only `#app-shell` — sidebar, header and content change with no full reload, and
  the red/blue accent follows via a `data-theme` flag. The layout emits a partial
  when the `X-Partial` header is set; the client swaps it, re-runs any inline
  scripts, and updates history. Pure progressive enhancement — every link/form
  still works without JS.
- Verified: `ContextSwitcherTest` (4); certify 137/137; live browser test —
  switching to a candidate context changed `/dashboard`→`/my-applications`,
  swapped the sidebar to the candidate menu, **without a page reload** (a JS
  marker survived the swap); switcher screenshot shows crown + Workspaces +
  Applying-to.

### Accept-first invitations + invite management
- Invitations are now **accept-first**: when you're invited by email, you don't
  silently join. A signed-in user reviews the workspaces that invited them at
  **/invites** and accepts or declines; the workspace appears (in the switcher)
  only after they accept. Pending invites surface in the context switcher (an
  amber "Invitations N" entry) and a freshly-invited user with no workspaces is
  funnelled straight to /invites after sign-in.
- New Core contract `InvitationInbox` (`pendingForEmail`), implemented by
  `InvitationService` and consumed by `ContextSwitcher` + `DashboardController`
  so decoupled layers can surface invites (§4). `InvitationService` gains
  `acceptOwn`/`declineOwn` (email-verified — you can't accept someone else's
  invite), `forWorkspace`, `updateRoles`, `revoke`, and `pendingForEmail`.
- New `InvitesController` (+ `invites.index` view). **Invite management** on the
  Members page: list pending invitations, change the roles they'll grant on
  acceptance, or revoke them (`/members/invitations/{id}/roles|revoke`).
- A candidate can already create a workspace and invite people by email; that
  flow now uses the same accept-first invitations + management.
- Verified: `InvitationFlowTest` (4 — surface/accept/membership, can't accept
  another's invite, decline, roles+revoke), `ContextSwitcherTest` (pending
  count); certify **143/143**; live HTTP smoke of the full funnel (invite →
  register → funnelled to /invites → accept → member); full suite green.

### Notes
- Repository reset to a clean slate before Phase 1 (previous placeholder README
  removed; recoverable from git history).

---

[Unreleased]: https://github.com/ammarnabileg/crm/tree/claude/vibrant-wright-vv65qw
