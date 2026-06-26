# HalaOps — Multi-Tenant SaaS Platform

A production-grade, **multi-tenant SaaS** built in **pure PHP** (no framework),
with **MySQL**, **Tailwind CSS** and vanilla JavaScript. Designed to be shipped
as a single uploadable package and installed entirely from the browser — **no
SSH, Composer, Artisan, npm or CLI** required by the person installing it.

> Bilingual by design (Arabic / English, full RTL & LTR), real RBAC, strict
> tenant isolation, and a bring-your-own-keys AI provider layer.

---

## Highlights

- **One users table.** Nobody is a "candidate", "HR" or "owner" row. Everyone is
  a `user`; capabilities come entirely from **roles, permissions, inheritance and
  policies** — never `if ($type === 'admin')`. The same person can be an owner of
  one company, a member of another, and a platform super-admin, all on one id.
- **True multi-tenancy.** Each company is an isolated tenant. Tenant-scoped models
  automatically constrain every query to the active company and **fail closed**
  (throw) if no tenant is set. Cross-tenant access requires an explicit, auditable
  `withoutTenantScope()` opt-out used only by platform/super-admin code.
- **No-CLI web installer.** A guided wizard runs requirement checks, configures &
  creates the database, runs migrations and seeders, creates the admin account,
  and writes `.env` — with a **live console** and **resume-from-last-step**
  recovery.
- **Data-driven subscriptions.** Plans are rows, not code. Ships with one plan
  (50 SAR / month) and supports unlimited plans without code changes.
- **Tenant AI provider layer.** The platform holds **no** AI keys. Each company
  supplies its own (OpenAI, Anthropic, Gemini, DeepSeek, Azure OpenAI, HeyGen…),
  stored encrypted at rest (AES-256-GCM) and resolved per tenant.
- **Zero runtime dependencies.** Custom PSR-4 autoloader, router, PDO query
  builder, view engine, sessions, CSRF, validation, encryption and i18n — all
  hand-built so deployment is "upload and go".

## Tech stack

| Layer     | Choice                                              |
|-----------|-----------------------------------------------------|
| Language  | PHP 8.2+ (no framework)                             |
| Database  | MySQL / MariaDB (InnoDB, utf8mb4, foreign keys)     |
| Frontend  | Server-rendered PHP templates + Tailwind CSS + JS   |
| Sessions  | Native PHP sessions, hardened cookies, file store   |
| Security  | CSRF tokens, Argon2id/bcrypt hashing, AES-256-GCM, security headers, rate limiting |

## Project structure

```
app/
  Core/            Micro-framework: Application, Router, Request/Response,
                   Database, QueryBuilder, Model, View, Session, Validator,
                   Hash, Encrypter, Mailer, Logger, Middleware/
  Controllers/     HTTP controllers (Auth, App, Setup)
  Models/          Active-record models (User, Company, Membership, Role, …)
  Http/Middleware/ Authenticate, EnsureTenant, RequirePermission, Throttle…
  Services/        Auth, Tenancy, Rbac, Install, AI
bootstrap/         Autoloader + application bootstrap
config/            app, database, session, auth, rbac, mail, middleware
database/
  migrations/      Numbered, MySQL-specific schema migrations
  seeders/         Idempotent baseline data (permissions, roles, plan)
resources/
  views/           Templates (layouts, auth, app, setup, errors, partials)
  css/app.css      Tailwind source (compiled to public/assets/css/app.css)
  lang/            en / ar translations
public/            Web root: index.php front controller + compiled assets
routes/web.php     Route definitions
storage/           logs, cache, sessions, framework state (writable)
```

## Installation (production)

1. Upload the package so the web server's document root points at `/public`
   (a root `.htaccess` is included for hosts where you cannot change the docroot).
2. Ensure `storage/` and the project root are writable.
3. Visit the site in a browser — you'll be taken to **/install**.
4. Fill in the database details and admin account, click **Run installation**,
   and watch the live console. That's it.

No command line is ever required.

## Development

The shipped package contains pre-compiled CSS and needs no build step. For
development only:

```bash
npm install                 # one-time, for the Tailwind build
npm run build:css           # compile resources/css/app.css -> public/assets/css/app.css
php -S 127.0.0.1:8000 server-router.php   # run locally
```

## Security model

- **Authentication:** one login / register / forgot / reset for the whole
  platform. Passwords hashed with Argon2id (bcrypt fallback), transparent rehash
  on login, anti-enumeration on reset, login throttling.
- **Authorization (RBAC):** permissions are a global catalogue; roles grant
  permissions and inherit from a parent role; a user's effective permissions are
  the union of their global roles and the tenant roles on their membership.
  Super-admins bypass checks; policy gates add context-aware ("own record") rules.
- **Tenant isolation:** enforced at the model layer, fail-closed, with an explicit
  escape hatch confined to system code.
- **Transport & app:** CSRF on all state-changing requests, hardened session
  cookies, security headers, and encrypted storage for tenant secrets.

## Roadmap

- [x] Phase 1 — Core foundation (micro-framework)
- [x] Phase 2 — No-CLI web installer with live console & recovery
- [x] Phase 3 — Multi-tenancy, single users table, RBAC, authentication
- [ ] Phase 4 — Companies & subscriptions management UI
- [ ] Phase 5 — Tenant AI provider layer (settings UI)
- [ ] Phase 6 — Per-role onboarding & dashboards
