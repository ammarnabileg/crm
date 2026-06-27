# Changelog

All notable changes to **HalaOps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file is part of the `/docs` single source of truth. See [00-README](00-README.md)
for how the documentation set is organized and the documentation-first development rule.

## [Unreleased]

### Added
- **Development Workflow + Continuous Project Audit Bibles adopted (governance).** Two binding process constitutions captured documentation-first: [49-Development-Workflow](49-Development-Workflow.md) (the 16-stage per-feature lifecycle, feature-completeness checklist, Definition of Done, self/regression testing, routing/DB/API/security/performance/refactoring rules, stop conditions, no fake completion) and [50-Continuous-Project-Audit](50-Continuous-Project-Audit.md) (after every phase, audit the WHOLE project — broken pages/routes/buttons, unused APIs, missing permissions, multi-tenant/RBAC/security/performance/DB/UI issues, doc↔code drift — and fix before the next phase). Indexed in [00-README](00-README.md) and cross-linked from [47](47-Enterprise-Architecture-Standards.md).
- **Setup & Installer Bible realized — no-CLI installer + operations suite.** The web installer now conforms to the [Setup Bible](32-Setup-Installer.md): a 12-step wizard at **`/setup`** (Welcome → System/Server/PHP checks with plain-language fixes → Database → Environment keys → Storage tree → Permissions + **Auto-Fix** → Mail + **Send Test Email** → AI-providers info → Super Admin → **Final Health Check**), with a **real progress bar**, live console, resume-on-failure, auto-repair of storage/permissions, and a **real final validation** (creates a workspace/user in a rolled-back transaction + storage/mail probes) gating finalize. Plus a dashboard **operations suite** (super-admin, `system.manage`, no terminal): **Diagnostics**, **Maintenance mode** (503 gate with super-admin bypass), **Backup & Restore** (pure-PHP SQL dump + ZIP, no mysqldump), **Environment Editor** (safe `.env` keys, secrets masked), and **Log Viewer** (level-coloured tail + suggested fixes). **Verified end-to-end: clean fresh install (43 migrations, health + final validation PASS, `.env` + lock written), all ops pages 200 for super-admin / 403 for others, maintenance gate + bypass + backup file confirmed, 52 tests green.** See [32-Setup-Installer](32-Setup-Installer.md) + [33-System-Diagnostics §Post-Install Operations](33-System-Diagnostics.md#post-install-operations-suite-no-terminal).
- **Full database build — blueprint realized (migrations `0018`–`0032`).** The entire approved Database Bible is now implemented and verified against MySQL/MariaDB: the D0 foundation (reference data + configuration-driven `lookup_categories`/`lookup_values` + `system_modules` registry + `permissions.module_id`/`action`), every domain D1–D10 and the D0 polymorphic shared tables (each a create migration + a companion idempotent FK migration so every table exists before any foreign key — the jobs↔pipelines cycle and all cross-domain refs are handled), the D2 support tables, the built→blueprint renames (`activity_log`→`activity_logs`, `permission_role`→`role_permissions`, `membership_role`→`membership_roles`, `user_role`→`user_roles`, `ai_credentials`→`tenant_ai_keys`), and the no-ENUM cutover (`workspaces`/`subscriptions`/`memberships`/`users` statuses and `plans.interval` → status-table / `lookup_values` FKs). Config seeds added (`ReferenceDataSeeder`, `LookupSeeder`). **Verified from scratch: 43 migrations / 0 failures, 167 tables (166 + ledger), 0 ENUM columns, 451 FKs, full reference/lookup/status seed; app test suite green; registration + dashboard flows verified.** See [database/00-Database-Bible §Migration Status](database/00-Database-Bible.md#migration-status--blueprint-realized).
- **Complete official `/docs` specification set** — the single source of truth, authored documentation-first per [00-README](00-README.md). 47 numbered documents (00–46) plus this CHANGELOG, ~13,000 lines, every file following the mandatory section template, fully cross-linked. Coverage:
  - **Overview & business:** [00-README](00-README.md), [01-Project-Vision](01-Project-Vision.md), [02-Business-Rules](02-Business-Rules.md) (stable `BR-xxx` IDs), [45-Future-Roadmap](45-Future-Roadmap.md).
  - **Architecture:** [03-System-Architecture](03-System-Architecture.md), [04-Folder-Structure](04-Folder-Structure.md), [29-API-Architecture](29-API-Architecture.md), [30-Frontend-Architecture](30-Frontend-Architecture.md), [31-Backend-Architecture](31-Backend-Architecture.md).
  - **Data:** [05-Database-Architecture](05-Database-Architecture.md), [06-ERD](06-ERD.md) (all 36 tables, built + planned), [27-Storage-System](27-Storage-System.md), [28-Search-System](28-Search-System.md).
  - **Identity & access:** [07-RBAC](07-RBAC.md), [08-Multi-Tenant](08-Multi-Tenant.md), [09-Authentication](09-Authentication.md), [10-Authorization](10-Authorization.md), [11-Permissions-Matrix](11-Permissions-Matrix.md).
  - **Company & money:** [12-Workspace-Management](12-Workspace-Management.md), [13-Subscription-System](13-Subscription-System.md), [14-Billing-System](14-Billing-System.md), [15-Payment-Gateways](15-Payment-Gateways.md).
  - **AI:** [16-AI-Architecture](16-AI-Architecture.md), [17-AI-Providers](17-AI-Providers.md), [18-AI-Interview-Engine](18-AI-Interview-Engine.md).
  - **Journeys:** [19-Candidate-Journey](19-Candidate-Journey.md), [20-Recruiter-Journey](20-Recruiter-Journey.md), [21-HR-Journey](21-HR-Journey.md), [22-SuperAdmin-Journey](22-SuperAdmin-Journey.md).
  - **Domain workflows:** [23-Interview-Workflow](23-Interview-Workflow.md), [24-Job-Lifecycle](24-Job-Lifecycle.md), [25-Application-Lifecycle](25-Application-Lifecycle.md), [26-Notification-System](26-Notification-System.md).
  - **Setup & ops:** [32-Setup-Installer](32-Setup-Installer.md), [33-System-Diagnostics](33-System-Diagnostics.md), [43-Deployment](43-Deployment.md), [44-Production-Checklist](44-Production-Checklist.md).
  - **Security & reliability:** [34-Security](34-Security.md), [35-Performance](35-Performance.md), [36-Scalability](36-Scalability.md), [37-Logging](37-Logging.md), [38-Audit-System](38-Audit-System.md).
  - **Testing & standards:** [39-Testing-Strategy](39-Testing-Strategy.md), [40-QA-Checklist](40-QA-Checklist.md), [41-Coding-Standards](41-Coding-Standards.md), [42-Code-Review-Checklist](42-Code-Review-Checklist.md).
  - **Self-audit:** [46-Architecture-Review](46-Architecture-Review.md) — independent architecture review and verification report.
  - **Enterprise standard:** [47-Enterprise-Architecture-Standards](47-Enterprise-Architecture-Standards.md) — binding layered architecture, SOLID, DI, Repository/Service/DTO, Events, Cache/Storage/Search/Queue/AI/Notification contracts, Settings, Feature Flags, Audit, Soft Delete, UUID, Policies.
- **Enterprise architecture foundation (code)**, built in tested increments against doc 47 and non-breaking to the verified Phase 1–3 flows:
  - **DI container** with reflection autowiring + interface→concrete binding; core services aliased by type.
  - **Contracts** for swappable seams: `CacheStore`, `RepositoryInterface` (+ User/Company), `EventDispatcherInterface`, `AuditLogger`.
  - **Cache layer** (`FileStore` default + `ArrayStore`); **Settings** system + **Feature flags** (per-tenant, cached, config defaults).
  - **Repository pattern** (`BaseRepository`, tenant- and soft-delete-aware) + concrete repositories; immutable **DTOs**.
  - **Soft deletes** + **RFC-4122 v4 UUIDs** on core entities (migration `0016`); audit gains `old_values`/`new_values`/`device`.
  - **Domain enums**; **domain events** (`UserRegistered`, `CompanyCreated`) + **listeners** writing the audit trail; event-driven **AuditLogger**.
  - **CompanyPolicy** + policy-aware `AccessControl` (permission + policy + tenant membership).
  - Registration refactored to a thin controller → **RegistrationService** (DTO + repository + events).
  - In-house **zero-dependency test runner** (`tests/`) with **52 passing tests** (116 assertions): container, cache, enums, settings/flags, repositories (CRUD/UUID/soft-delete/tenant scope), DTOs, events, audit (old/new), and the company policy.

### Fixed
- Added the mandatory **Security** section (per the §15 template) to [35-Performance](35-Performance.md), [36-Scalability](36-Scalability.md), and [37-Logging](37-Logging.md), surfaced by the self-audit.
- Company/membership/role/subscription rows created via service raw-inserts now generate UUIDs (previously NULL), keeping EAS-8 consistent across the Model and service write paths.

### Notes
- Documentation describes both **built** areas (verified against `app/`, `database/migrations/`, `config/`, `routes/web.php`) and **planned** areas (specified as planned, consistent with the canonical schema).
- Self-audit verification ([46-Architecture-Review](46-Architecture-Review.md)): all numbered docs contain the 14 mandatory sections; **0 broken cross-references**; permission keys, role names, and state-machine enums are consistent across the whole set.

## [1.0.0] — 2026-06-26

The first production release of HalaOps, delivering Phases 1–3: a pure-PHP,
no-framework, multi-tenant SaaS foundation with a no-CLI installer, strict
tenant isolation, a single users table with full RBAC, and platform-wide
authentication.

### Added

#### Phase 1 — Core foundation (micro-framework)
- Zero-dependency core under `app/Core/`: `Application`, `Container`, `Config`, `Env`, `Router`, `Route`, `Request`, `Response`, `Session`, `View`, `Database`, `QueryBuilder`, `Model`, `Validator`, `Hash`, `Encrypter`, `Mailer`, `Logger`, `Translator`, `Controller`.
- Custom PSR-4 autoloader and application bootstrap (`bootstrap/autoload.php`, `bootstrap/app.php`); no `vendor/`.
- Middleware pipeline with `SecurityHeaders` and `VerifyCsrfToken`, plus alias-based middleware resolution (e.g. `permission:`, `throttle:`).
- Single PDO database layer (ERRMODE_EXCEPTION, real prepared statements, utf8mb4) with nested transactions via SAVEPOINTs; fully parameterized fluent `QueryBuilder`.
- Plain-PHP view engine with layout inheritance and output escaping via `e()`.
- AES-256-GCM `Encrypter`, Argon2id `Hash` (bcrypt fallback, needs-rehash), bilingual `Translator` (AR/EN, RTL detection).
- Helper functions: `app()`, `config()`, `env()`, `view()`, `redirect()`, `url()`, `route_to()`, `e()`, `auth()`, `tenant()`, `access()`, `can()`, `__()`, `encrypt_value()`, `decrypt_value()`, and more.

#### Phase 2 — No-CLI web installer
- Browser-based installer at `/install` (`app/Controllers/Setup/InstallController.php`): requirements → database → migrate → seed → admin → finalize.
- Live AJAX install console and resume-from-last-step recovery.
- Writes `.env` and an install lock file; install gating in the application kernel so the app routes to the installer until set up.
- Migration runner and `migrations` tracking table; migrations run outside transactions (MySQL DDL auto-commit).

#### Phase 3 — Multi-tenancy, single users table, RBAC, authentication
- **Single `users` table** — no per-type tables; capability is entirely role-derived.
- **Row-level multi-tenancy** — tenant-scoped models auto-filter by `company_id` and **fail closed** (throw) when no tenant is active; `withoutTenantScope()` escape hatch for system/super-admin code (`app/Services/Tenancy/TenantManager.php`, `CompanyService.php`).
- **RBAC** — global permission catalogue, global and tenant roles with single-parent inheritance, policy gates (`app/Services/Rbac/AccessControl.php`, `RbacManager.php`); permissions and default tenant roles are data-driven in `config/rbac.php`.
- **Authentication** — one login/register/forgot/reset for the whole platform (`app/Controllers/Auth/*`): Argon2id hashing with transparent rehash, login throttling (5 attempts / 900s lockout), anti-enumeration password reset (hashed tokens, 60-minute TTL), session regeneration on login.
- **Company provisioning** — atomic creation of company, owner membership, default roles, owner role assignment, and a trial subscription; registrant becomes Owner.
- **Database schema** — migrations `0001`–`0015`: `users`, `companies`, `memberships`, `roles`, `permissions`, `permission_role`, `membership_role`, `user_role`, `plans`, `subscriptions`, `ai_credentials`, `password_resets`, `settings`, `onboarding_progress`, `activity_log` (plus the `migrations` table). All InnoDB/utf8mb4 with real foreign keys and indexes.
- **Baseline seed data** (`database/seeders/DatabaseSeeder.php`, idempotent): full permission catalogue, the global `super-admin` role, and the single "Standard" plan (50.00 SAR, monthly, 14-day trial).
- **Tenant AI credential storage** — `ai_credentials` table holding per-tenant, AES-256-GCM-encrypted provider keys (one row per provider, `UNIQUE(company_id, provider)`); the platform stores no AI keys of its own.
- **Bilingual UI** — Arabic/English views and language files (`resources/lang/{en,ar}/`), full RTL/LTR support, compiled Tailwind CSS shipped at `public/assets/css/app.css` (no build step on the buyer's server).
- **Audit logging** — `activity_log` records security/business events with actor, subject, IP, and user agent.

### Security
- CSRF protection on all state-changing requests; hardened session cookies (httponly, samesite=Lax, secure on HTTPS).
- AES-256-GCM authenticated encryption for tenant secrets; Argon2id password hashing.
- Prepared statements only; output escaping in templates; security headers; rate limiting; anti-enumeration on auth flows.
- Fail-closed tenant isolation as the primary cross-tenant data-leak defense.

[Unreleased]: https://example.com/halaops/compare/v1.0.0...HEAD
[1.0.0]: https://example.com/halaops/releases/tag/v1.0.0
