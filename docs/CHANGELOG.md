# Changelog

All notable changes to **HalaOps** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file is part of the `/docs` single source of truth. See [00-README](00-README.md)
for how the documentation set is organized and the documentation-first development rule.

## [Unreleased]

### Added
- Official `/docs` specification set (the single source of truth), authored documentation-first per [00-README](00-README.md):
  - [00-README](00-README.md) — documentation index, conventions, documentation-first rule, the table of all 47 documents, reading order, and the cross-reference map.
  - [01-Project-Vision](01-Project-Vision.md) — vision, mission, target market, value proposition, product pillars, personas (Super Admin, Owner, Administrator, HR Manager, Recruiter, Hiring Manager, Interviewer, Member, Candidate), success metrics, and non-goals.
  - [02-Business-Rules](02-Business-Rules.md) — the authoritative enumerated business rules across identity, tenancy, RBAC, companies, subscriptions, jobs, applications, interviews, AI, and billing, with stable `BR-xxx` identifiers other documents cite.
  - [45-Future-Roadmap](45-Future-Roadmap.md) — phased roadmap (Phases 1–3 delivered; 4–6 next; 7–9 future) and a five-year scalability outlook.
  - This CHANGELOG.

### Notes
- Documentation describes both **built** areas (verified against `app/`, `database/migrations/`, `config/`, `routes/web.php`) and **planned** areas (specified as planned, consistent with the canonical schema).

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
