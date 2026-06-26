# 32 — Setup Installer (مثبّت الإعداد)

The browser-based, no-CLI web installer that stands HalaOps up from an uploaded package — requirements → database → migrate → seed → admin → finalize — with a live AJAX console and resume-from-last-step recovery.

## Related Documents

- [33-System-Diagnostics](33-System-Diagnostics.md)
- [43-Deployment](43-Deployment.md)
- [05-Database-Architecture](05-Database-Architecture.md)
- [07-RBAC](07-RBAC.md)
- [09-Authentication](09-Authentication.md)
- [34-Security](34-Security.md)
- [44-Production-Checklist](44-Production-Checklist.md)

## Purpose (الهدف)

The installer (`/install`) is the single, browser-driven path that turns a freshly uploaded HalaOps package into a running platform. A non-technical buyer uploads the files (via cPanel File Manager, FTP, or an unzip), opens the site in a browser, and clicks through a six-step wizard. There is **no SSH, no Composer, no Artisan, no npm, no Node, and no terminal** at any point — every action a CLI would normally perform is exposed as an idempotent JSON endpoint and orchestrated by `app/Services/Install/InstallManager.php`, surfaced through `app/Controllers/Setup/InstallController.php`, and rendered by `resources/views/setup/install.php`.

The six canonical steps (the public `InstallManager::STEPS` constant) are:

1. `requirements` — verify the host can run HalaOps.
2. `database` — validate DB credentials and create the schema database.
3. `migrate` — create all tables from `database/migrations/`.
4. `seed` — seed permissions, roles, and the default plan.
5. `admin` — create the first super-admin user.
6. `finalize` — write `.env`, place the install lock, and lock the installer down.

## Why It Exists (سبب وجوده)

HalaOps is sold as an upload-and-install product to thousands of companies, many on cheap shared hosting where the only access the buyer has is a control panel and a browser. A traditional `php artisan migrate` / `composer install` flow is impossible there and intimidating everywhere else. The installer exists to:

- **Remove the CLI entirely.** Per the platform principle "No CLI for the person installing", every install action runs as ordinary HTTP requests inside the app the buyer just uploaded.
- **Make installation safe to retry.** Shared hosts time out, drop connections, and impose memory limits. Each step records completion to a state file so a failure resumes from the last good step instead of corrupting a half-migrated database.
- **Fail closed and lock down.** Until the lock file exists the app serves only the installer; once finalized the installer refuses to mutate anything and visitors are sent to login. This prevents a re-install attack and leaving an open setup wizard on a production URL.
- **Give the buyer confidence.** A live console streams each check and each migration so the buyer sees exactly what happened and what to fix when something fails.

## Architecture

Three real components collaborate, plus two storage-only artifacts.

| Component | File | Responsibility |
|-----------|------|----------------|
| Install service | `app/Services/Install/InstallManager.php` | The brain. Owns `STEPS`, the state file, the lock file, and one method per step (`checkRequirements`, `configureDatabase`, `runMigrations`, `runSeeders`, `createAdmin`, `finalize`). Idempotent and resumable. |
| Install controller | `app/Controllers/Setup/InstallController.php` | The HTTP surface. `index()` renders the wizard; one POST action per step returns the JSON envelope `{ok, message, ...}`. `guard()` normalizes success/failure and blocks all writes once installed. |
| Install view | `resources/views/setup/install.php` | The wizard UI: DB/admin/app forms, the step checklist, and the live console. The vanilla-JS controller runs requirements then POSTs each step in order, rendering results into `#console`. |
| Migration runner | `database/Migrator.php` | Discovers `database/migrations/NNNN_*.php`, tracks applied ones in the `migrations` table, runs pending ones in order with a per-migration progress callback. |
| Seeder | `database/seeders/DatabaseSeeder.php` | Idempotent baseline data: permission catalogue, global super-admin role, default "Standard" plan. |

Two files live under `storage/framework/` and are **never web-served** (the root `.htaccess` blocks the entire `storage/` tree):

- `storage/framework/install_state.json` — the resume ledger. JSON of `{"completed": ["requirements", ...], "config": {...}}`. The `config` key transiently holds the DB credentials and admin metadata between steps.
- `storage/framework/installed` — the **lock file**. Its presence (together with a present `.env`) means "installed". Written in `finalize()` with a timestamp.

The front controller / `Application` consults `InstallManager::isInstalled()` (lock file **and** `.env` both present) during boot to decide whether to gate the whole app behind the installer or to serve the application normally. Routes (`routes/web.php`) expose the installer under the `/install` prefix: a `GET /install` plus six POST endpoints (`install/requirements`, `install/database`, `install/migrate`, `install/seed`, `install/admin`, `install/finalize`).

```mermaid
flowchart LR
    Browser["Browser /install"] -->|"GET"| Index["InstallController::index"]
    Index --> View["setup/install.php\n(forms + live console)"]
    View -->|"POST per step"| Ctrl["InstallController\n(guard envelope)"]
    Ctrl --> Mgr["InstallManager"]
    Mgr -->|"read/write"| State[("storage/framework/\ninstall_state.json")]
    Mgr -->|"migrate"| Migrator["database/Migrator.php"]
    Migrator --> DB[("MySQL")]
    Mgr -->|"seed"| Seeder["DatabaseSeeder.php"]
    Mgr -->|"finalize"| Env[[".env"]]
    Mgr -->|"finalize"| Lock[("storage/framework/\ninstalled (lock)")]
```

## Workflow

### Wizard sequence

```mermaid
sequenceDiagram
    participant U as Buyer (Browser)
    participant V as install.php (JS)
    participant C as InstallController
    participant M as InstallManager
    participant DB as MySQL

    U->>V: Open /install
    V->>C: GET /install (index)
    C->>M: isInstalled()? state() / nextStep()
    M-->>C: not installed, next=requirements
    C-->>V: render wizard (forms + console)
    V->>C: POST install/requirements (auto on load)
    C->>M: checkRequirements()
    M-->>C: {checks[], passed}
    C-->>V: JSON; console prints each check
    U->>V: Fill DB + admin + app, click "Run installation"
    V->>C: POST install/requirements (re-check)
    C-->>V: passed=true
    loop database → migrate → seed → admin → finalize
        V->>C: POST install/<step> (FormData + CSRF)
        C->>M: guard(): isInstalled? then step method
        M->>DB: connect / DDL / inserts
        M->>M: markComplete(step, config)
        M-->>C: result (+ per-migration log for migrate)
        C-->>V: {ok:true, message, log?}
        V->>V: console prints lines; mark step ✓
    end
    M->>M: write .env + lock; unlink install_state.json
    C-->>V: {ok:true, redirect:/login}
    V->>U: redirect to login after 1.2s
```

### What each step does

1. **requirements** — `InstallController::requirements()` calls `InstallManager::checkRequirements()`. It checks PHP ≥ 8.2; required extensions `pdo_mysql, mbstring, openssl, json, fileinfo, curl`; recommended extensions `gd, intl, zip`; that `storage`, `storage/logs`, `storage/cache`, `storage/sessions`, `storage/framework` exist and are writable (creating them at `0775` if missing via `ensureWritable()`); and that the project root is writable so `.env` can be written. Returns `{checks:[{name, ok, value, required}], passed}`. `passed` is true only when every **required** check is ok; recommended misses are informational. This step is read-only and is **not** recorded as completed — it is re-run every page load and again immediately before installation.

2. **database** — `configureDatabase()` trims and validates the inputs, rejecting an empty database name or one not matching `^[A-Za-z0-9_]+$` (defends against injection into the `CREATE DATABASE` DDL, which cannot be parameterized). It opens a server-level PDO connection (no DB selected, `ATTR_TIMEOUT => 5`, `ERRMODE_EXCEPTION`) to verify the credentials, then runs `CREATE DATABASE IF NOT EXISTS ... utf8mb4 / utf8mb4_unicode_ci` and `USE`. On success it calls `markComplete('database', ['db' => {host, port, database, username, password}])`, persisting the credentials to the state file for later steps and for `finalize`.

3. **migrate** — `runMigrations($report)` builds a `Database` from the saved DB config (`makeDatabase()`) and hands it to `database/Migrator.php`. The migrator ensures the `migrations` table exists, computes pending files from `database/migrations/*.php` (sorted, so `0001_…` runs before `0015_…`), and runs each: `require $file` → `$migration->up($db)` → record the row → invoke the `$report($name, $ok, $error)` callback. **Migrations do not run inside a transaction** because MySQL implicitly commits on DDL; instead each migration is recorded immediately after it succeeds, so a re-run skips already-applied migrations. The controller collects the per-migration log into the JSON response so the console can render a tick or cross per table. The step is marked complete only when `result.failed === null`.

4. **seed** — `runSeeders()` does `require database/seeders/DatabaseSeeder.php` and calls `->run($db)`. The seeder is fully idempotent: `RbacManager::syncPermissions()` upserts the permission catalogue, `ensureSuperAdminRole()` creates/returns the global `super-admin` role, and `seedDefaultPlan()` inserts the "Standard" plan (50.00 SAR, monthly, 14-day trial) only if a plan with slug `standard` does not already exist. Marked complete on success.

5. **admin** — `createAdmin()` validates name/email/password (valid email, password ≥ 8 chars), then **upserts**: if a user with that email already exists it updates name/password/status (recovery-friendly), otherwise it inserts an `active`, email-verified user with locale `en`. It then assigns the global super-admin role via `RbacManager::ensureSuperAdminRole()` + `assignGlobalRole()`. Stores `admin_user_id` and `admin_email` in state and marks complete.

6. **finalize** — `finalize()` first re-asserts that `database, migrate, seed, admin` are all complete (refusing to lock a half-configured install). It assembles the `.env` map — `APP_ENV=production`, `APP_DEBUG=false`, a freshly generated `APP_KEY` (`Encrypter::generateKey()`), `APP_URL`, `APP_TIMEZONE=Asia/Riyadh`, `APP_CURRENCY=SAR`, the DB block from saved state, and `SESSION_SECURE=true` when the URL is `https` — and writes it via `writeEnv()` (values quoted/escaped as needed). It then writes the lock file `storage/framework/installed`, marks `finalize` complete, and **deletes `install_state.json`** so the credentials no longer sit on disk. The response carries `redirect: /login`.

## Business Rules

- **BR-INSTALL-1 — One canonical step order.** Steps always run in the order of `InstallManager::STEPS`: `requirements, database, migrate, seed, admin, finalize`. `nextStep()` returns the first not-yet-completed step.
- **BR-INSTALL-2 — Installed means lock + env.** `isInstalled()` is true only when both `storage/framework/installed` and `.env` exist. Either one alone is not "installed".
- **BR-INSTALL-3 — Installed app refuses setup.** Once installed, `index()` redirects to `/login` and every step endpoint returns HTTP 409 via `guard()`. The installer can never re-run destructively over a live install.
- **BR-INSTALL-4 — Resume, never restart.** Completed steps are recorded in `install_state.json`; re-running installation skips completed steps (migrations are also individually skipped if already applied).
- **BR-INSTALL-5 — No half-finalized state.** `finalize()` throws if any of `database, migrate, seed, admin` is incomplete, so the lock is never placed over an unusable install.
- **BR-INSTALL-6 — Production defaults.** `finalize()` always writes `APP_ENV=production` and `APP_DEBUG=false`; debug is never on after a fresh install.
- **BR-INSTALL-7 — Secrets are transient.** DB credentials live in `install_state.json` only between steps and are erased when `finalize()` unlinks the state file; the durable copy lives only in `.env`, which is web-inaccessible.
- **BR-INSTALL-8 — First user is super-admin.** The `admin` step creates exactly the platform super-admin (global role, `company_id` NULL); it does not create a tenant. Companies are created later by users per [12-Workspace-Management].

## Database Relations

The installer is the **producer** of the baseline schema and seed rows; it touches these tables directly (all defined in [05-Database-Architecture] §11):

- `migrations` (id, migration UQ, batch, executed_at) — created and populated by `Migrator`; one row per applied migration file.
- `users` (GLOBAL) — the `admin` step inserts/updates the super-admin row (`status=active`, `email_verified_at` set).
- `roles` (GLOBAL row, `company_id` NULL) — `ensureSuperAdminRole()` ensures the `super-admin` role (`is_system`).
- `permissions` (GLOBAL) + `permission_role` — `syncPermissions()` upserts the catalogue and links it to the super-admin role.
- `user_role` — `assignGlobalRole()` links the admin user to the super-admin role.
- `plans` (GLOBAL) — `seedDefaultPlan()` inserts the "Standard" plan when absent.

Migrations `0001`–`0015` create the full built schema (users, companies, memberships, roles, permissions, permission_role, membership_role, user_role, plans, subscriptions, ai_credentials, password_resets, settings, onboarding_progress, activity_log) before any other step runs.

## Permissions

The installer is **unauthenticated by design** — it runs before any user exists, so it cannot be gated by RBAC. Its access control is therefore environmental, not permission-based:

- **Pre-install:** the app is gated so only `/install` and its assets are reachable; there are no users, so no permission checks apply.
- **Post-install:** access is denied by `isInstalled()` (controller redirects to `/login`; endpoints return 409), not by a permission.
- The first account the installer creates is the platform **super-admin** (global `super-admin` role → all permissions, per [07-RBAC]). Everything the buyer does afterward, including health checks via `platform.diagnostics` (see [33-System-Diagnostics]), is gated by RBAC from that point on.

## Validation

- **Database name** — required; must match `^[A-Za-z0-9_]+$` (letters, digits, underscore). Rejected names raise a `RuntimeException` surfaced as a 422 with a human message.
- **Database credentials** — verified by an actual PDO connect with a 5-second timeout; connection failures return the underlying server message prefixed with "Could not connect to the database server:".
- **Admin** — name, email, and password all required; email must pass `FILTER_VALIDATE_EMAIL`; password must be ≥ 8 characters. Email is lower-cased and trimmed.
- **App** — `app_name` defaults to "HalaOps"; `app_url` defaults to the request scheme+host (`guessAppUrl()`); the URL's `https` prefix drives `SESSION_SECURE`.
- **Client-side** — the form uses HTML5 `required`/`minlength`/`type=email` and `form.reportValidity()` before submitting, but the server re-validates everything (never trust the client).

## Edge Cases

- **Mid-install timeout / connection drop.** State is written after each completed step, so reloading `/install` and clicking "Run installation" again resumes. Migrations already recorded are skipped; the seeder and admin steps are idempotent.
- **A single migration fails.** `Migrator::run()` stops at the failing file, returns `{ran, failed}`, and the console prints a cross next to that table with the exact error. The `migrate` step is **not** marked complete, so a retry re-attempts only the remaining migrations (already-applied ones are skipped).
- **Root not writable for `.env`.** The requirements check flags it; `writeEnv()` throws "Unable to write the .env file…" if it still fails at finalize. The fix is a control-panel permission change, after which finalize is retried.
- **`storage/` not writable.** Requirements flags each subdirectory; `ensureWritable()` attempts to create them at `0775` first. If the host forbids it, the buyer fixes permissions and re-checks.
- **Admin email already exists** (e.g. partial earlier run). `createAdmin()` updates the existing user instead of failing on the unique email constraint.
- **Re-opening `/install` after success.** `index()` sees `isInstalled()` and redirects to `/login`; no setup UI is shown.
- **Direct POST to a step after install.** `guard()` returns HTTP 409 `{ok:false, message:"The application is already installed."}` before running anything.
- **Unexpected non-JSON response** (e.g. host 500/HTML error page). The client's `call()` throws "Unexpected server response (HTTP n)" so the buyer is not left staring at a frozen spinner.
- **Finalize attempted early.** Throws "Cannot finalize: the '<step>' step has not completed yet." — surfaced as 422 in the console.

## Security

- **CSRF on every write.** All six POST endpoints sit inside the standard middleware stack; the view sends `X-CSRF-TOKEN` (from the `<meta name="csrf-token">`) and `X-Requested-With: XMLHttpRequest` on every `fetch`. `VerifyCsrfToken` rejects forged writes.
- **DDL injection defense.** Database and table names cannot be bound as PDO parameters; the database name is therefore whitelisted by regex, and migrations ship as code (not user input).
- **Secrets never web-served.** `install_state.json` and the lock live under `storage/framework/`; both the root `.htaccess` (`RewriteRule ^(app|bootstrap|config|database|resources|routes|storage)(/|$) - [F,L]`) and the dotfile guard block the path, and the file is deleted at finalize anyway.
- **`.env` protection.** The root `.htaccess` blocks `^(\.env|…)` and any dotfile (`<FilesMatch "^\.">`); the `public/.htaccess` also denies dotfiles in case a stray `.env` is copied into the docroot. Per [43-Deployment], the docroot should be `/public`, which keeps `.env` outside the served tree entirely.
- **Strong app key.** `finalize()` generates a fresh `APP_KEY` via `Encrypter::generateKey()` (base64-encoded 32 bytes) for AES-256-GCM; it is never shipped in the package.
- **Lock-down after install.** `BR-INSTALL-3` ensures the wizard cannot be replayed; a second installation cannot overwrite the live database or reset the admin password through the installer.
- **Password storage.** The admin password is hashed with Argon2id via `Hash::make()`; the plaintext is never written to state or `.env`.
- **No debug leakage.** `APP_DEBUG=false` is forced, so a post-install error never renders a stack trace to visitors.

## Performance

- The whole install is a handful of synchronous HTTP requests; there is no build step on the buyer's server (Tailwind is pre-compiled to `public/assets/css/app.css`).
- Migrations run sequentially and are the longest step; the per-migration callback streams progress so long runs feel responsive rather than hung.
- The DB connection test uses `ATTR_TIMEOUT => 5` to fail fast on bad hosts/credentials instead of hanging the request.
- The state file is small JSON written with `LOCK_EX`; reads/writes are negligible.
- Requirements checks are pure filesystem/`extension_loaded` calls — cheap enough to run on every page load and again before install.

## Testing

- **Unit (`InstallManager`):** `nextStep()` returns the first incomplete step; `markComplete()` is additive and de-duplicates; `isInstalled()` requires both lock and `.env`; `configureDatabase()` rejects empty and non-`[A-Za-z0-9_]` names; `finalize()` throws when a prerequisite step is incomplete; `finalize()` deletes `install_state.json` and writes `APP_DEBUG=false` + a non-empty `APP_KEY`.
- **Unit (`Migrator`):** `pending()` excludes applied migrations; `run()` records each applied migration and stops at the first failure returning `failed`; a second `run()` applies nothing when up to date.
- **Feature (HTTP):** `GET /install` renders the wizard when not installed and redirects to `/login` when installed; each POST step returns the `{ok, message}` envelope; posting a step after install returns 409; missing CSRF returns 419/403.
- **Feature (recovery):** simulate a failure after `migrate`, then re-run and assert `seed`/`admin`/`finalize` complete and the schema is intact.
- **Security:** a non-whitelisted database name is rejected; `.env`, `storage/framework/install_state.json`, and the lock file are not retrievable over HTTP; the created admin has the global `super-admin` role and an Argon2id hash.

## Future Expansion

- **Maintenance-mode migration screen.** `Migrator` is deliberately reusable; a super-admin "Update" screen (see [43-Deployment] zero-downtime updates) can run pending migrations on an already-installed instance, gated by `platform.diagnostics`/an update permission and a maintenance flag, without re-touching the installer.
- **Optional steps.** The `STEPS` array is the single source of order; mail configuration, sample-data seeding, or first-company creation could be added as additional resumable steps without changing the orchestration model.
- **Pre-flight bundle verification.** A checksum/signature check of the uploaded package could be added as a `requirements` sub-check.
- **CLI parity (optional).** A thin CLI entry point could call the same `InstallManager` methods for power users, while the browser remains the only supported path for buyers.
- **Multi-DB / per-tenant DB.** If tenancy evolves toward database-per-tenant ([08-Multi-Tenant], [36-Scalability]), `makeDatabase()` and the database step can branch on a provisioning strategy without altering the wizard UX.

## Open Questions

None at this time. The installer, its endpoints, the state/lock files, and the finalize contract are fully implemented and match this document.
