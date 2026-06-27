# CONFIGURATION GUIDE — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_STRUCTURE.md`, `PROJECT_CONSTITUTION.md`.

---

## 0. Purpose & Scope

This document designs the **configuration system** of HaHireAI: how settings are
declared, layered, resolved, and accessed, and how secrets are kept out of the
repository. It is **design on paper**; the runtime lives in the **Configuration
Loader** and **Environment Loader** of the Core Kernel (`ARCHITECTURE.md` §6,
Phase 7). Illustrative code is **EXAMPLE ONLY**, not source.

This guide defers to `PROJECT_CONSTITUTION.md` and `PROJECT_STRUCTURE.md`; on
conflict those win (Constitution §0). It governs **system/application
configuration**; **per-workspace settings** — tenant-facing preferences stored in
the database — are a different thing, owned by the **Settings module** and
`WORKSPACE_MODEL.md` (see §10). **MUST**, **MUST NOT**, **SHOULD**, **SHOULD
NOT**, **MAY** per [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

## 1. Design Principles

- **NO constants in code, NO globals.** Tunable values **MUST** be obtained through
  the **Config loader**; defining them as `const`/`define()` or reading global
  state is **forbidden** (Constitution §6; `ARCHITECTURE.md` §6). Genuine code
  constants that are part of a type (enum cases, class constants like
  `MAX_UPLOAD_BYTES`, Constitution §7) are not "configuration" — *tunable* values
  are.
- **Secrets come ONLY from the environment.** No secret, credential, key, or token
  is ever committed or placed in `config/` (Constitution §5, §10;
  `PROJECT_STRUCTURE.md` §5).
- **Declarative config, no logic.** `config/` and module `Config/` files return
  plain arrays; they **MUST NOT** open connections, perform I/O, or branch on
  runtime data beyond reading env values.
- **Typed, explicit, injected access.** Callers read config through a typed accessor
  with an explicit key and (where apt) a default; required values fail loudly
  (§7, §9). Config is resolved at boot and provided via the container
  (`SERVICE_CONTAINER.md`); business logic **MUST NOT** reach a global config
  singleton (no service locator — `ARCHITECTURE.md` §6).

## 2. Layered Configuration Model

Configuration is resolved from **four layers**, each overriding the one above it
when keys collide. From lowest precedence (base) to highest (override):

```
┌──────────────────────────────────────────────────────────────┐
│ 1. Environment (.env / real env)  ← secrets + per-env values   │  base inputs
├──────────────────────────────────────────────────────────────┤
│ 2. config/ files                  ← global declarative defaults │
├──────────────────────────────────────────────────────────────┤
│ 3. module Config/ files           ← per-module declarative cfg  │
├──────────────────────────────────────────────────────────────┤
│ 4. runtime / workspace settings   ← DB-backed, request-scoped   │  highest
└──────────────────────────────────────────────────────────────┘
```

- **Layer 1 — Environment.** The raw inputs that differ per environment (local /
  staging / production) and **all** secrets. Read by the Environment Loader from
  `.env` (local) or the real process environment (servers). `config/` files
  *consume* these values; application code does **not** read env directly (§4).
- **Layer 2 — `config/` files.** Global, declarative defaults for the platform
  (`PROJECT_STRUCTURE.md` §2). These map env values into a structured, typed
  shape (e.g. `config/database.php`, `config/app.php`).
- **Layer 3 — module `Config/` files.** Each module ships its own configuration
  under `app/Modules/<Module>/Config/` (`PROJECT_STRUCTURE.md` §4), namespaced by
  module so keys never collide across modules (§8).
- **Layer 4 — runtime / workspace settings.** Values resolved at request time:
  app-level runtime toggles and, distinctly, **per-workspace settings** read from
  the Settings module/DB (§10). These are the most specific and win where they
  apply.

> **Precedence summary.** For a given key, the **most specific defined layer
> wins**: workspace/runtime > module `Config/` > `config/` > env-derived default.
> Layers do not deep-merge arbitrary structures by default; a key defined at a
> higher layer **replaces** the lower value unless a config file is explicitly
> authored to merge (§6).

## 3. Environment Layer (`.env`)

- `.env` holds environment-specific values and secrets and is **git-ignored**. A
  committed **`.env.example`** (`PROJECT_STRUCTURE.md` §2) documents every
  required key with safe placeholder values and **MUST** be kept in sync — a new
  required variable without an `.env.example` entry is a defect.
- The Environment Loader parses `.env` **once at boot**, before configuration is
  assembled (`ARCHITECTURE.md` §7), and applies declared **defaults** and basic
  **type coercion** (e.g. `"true"`→bool, numeric strings→int).
- In production the real **process environment** is the source of truth; `.env` is
  primarily a local-development convenience. Behavior is identical regardless of
  source.
- Environment values are read **only** by `config/` (and where unavoidable, the
  bootstrap). Modules and business code read configuration, never `getenv()`.

## 4. The "NO constants / NO globals" Rule

- **Every tunable is a config key.** A magic number, path, feature toggle, limit,
  provider name, or external URL **MUST** be a configuration key — not an inline
  literal, a `define()`, a `const` used as a setting, or a `$GLOBALS` entry
  (Constitution §6).
- **No env access outside config assembly.** `getenv()`/`$_ENV` from a controller,
  service, or domain class is forbidden; those layers receive typed config via
  injection. This keeps the env surface auditable in one place and lets layer 4
  override cleanly.
- **Why.** Centralizing values makes them testable, overridable per environment and
  per workspace, cacheable (§5), and free of hidden global coupling — the same
  rationale as named-route URL generation (`ROUTING_GUIDE.md`).

## 5. Configuration Caching (later phase)

- Assembling configuration from many files on every request is wasteful. The
  Kernel **MAY** compile the merged **layers 1–3** into a single cached artifact
  in `storage/` (git-ignored; `PROJECT_STRUCTURE.md` §2) to satisfy the
  performance budgets (Constitution §11). **Caching is implemented in a later
  phase**; the design simply must not preclude it.
- **Cacheable layers only.** Layers 1–3 are static for the life of a deploy and
  are safe to cache. **Layer 4 (runtime/workspace) is request-scoped and MUST
  NOT be baked into the static cache** — it is resolved live per request/tenant.
- The config cache is **invalidated on deploy** (and via an explicit clear step);
  stale config is a defect. Because secrets are read from the environment at boot,
  a cached artifact **MUST NOT** persist secret material to disk in plaintext.

## 6. Resolution & Precedence Rules

1. The Environment Loader loads layer 1 and validates required vars (§9).
2. The Configuration Loader loads layer 2 (`config/`) then layer 3 (each **enabled**
   module's `Config/`, via the Module Registry — `ARCHITECTURE.md` §6), producing a
   merged, typed tree. Disabled modules contribute nothing (Constitution §9).
3. At request time, layer 4 (runtime/workspace) overrides where applicable.
4. **Most-specific-wins:** for any key the highest defined layer supplies the value;
   lower layers are the fallback. Module config is namespaced (§8) so modules cannot
   clobber one another, and cross-module reads go through contracts, not config
   (`ARCHITECTURE.md` §4). A key undefined in every layer is handled per §9
   (required → boot failure; optional → explicit caller default).

## 7. Typed Config Access

- Configuration is read through a **typed accessor** keyed by a dotted path that
  encodes the layer/namespace, e.g. `app.timezone`, `database.connections.mysql`,
  `modules.jobs.max-active-postings`.
- Accessors are **type-asserting**: a caller requests a value as the type it
  expects (string/int/bool/array), and a type mismatch is an error, not a silent
  coercion at the call site.
- A caller **MAY** supply a default for **optional** keys; **required** keys
  **MUST NOT** be silently defaulted — their absence is a configuration defect
  (§9).
- The accessor is **injected** (constructor injection via the container); code
  **MUST NOT** reach a global singleton (`ARCHITECTURE.md` §6).
- Configuration is treated as **read-only at runtime**; nothing mutates the loaded
  config tree (immutability — Constitution §6). Tenant-changeable values live in
  layer 4 (§10), not by mutating layers 1–3.

## 8. Per-Module Configuration

- Each module owns `app/Modules/<Module>/Config/` and ships sensible, secret-free
  defaults (`PROJECT_STRUCTURE.md` §4; module standards, Constitution §9: a
  module **MUST** ship its configuration).
- Module config is **namespaced by module** (conventionally under a
  `modules.<module>.*` path) so keys are collision-free and discoverable.
- A module **MUST NOT** read another module's config keys; if it needs another
  module's behavior, it depends on that module's **Contract** or an **event**
  (`ARCHITECTURE.md` §4; `API_GUIDELINES.md` Part A). Configuration is not a
  cross-module API.
- Module config files obey naming rules: **kebab-case `.php` returning an array**
  (`DIRECTORY_STANDARD.md` §3), declarative only.

## 9. Validation of Required Env Vars at Boot

- The Environment Loader **MUST** validate that all **required** environment
  variables are present and well-typed **at boot**, before the kernel serves any
  request (`ARCHITECTURE.md` §7). A missing/invalid required var **MUST** cause a
  **fast, explicit boot failure** with a message naming the offending key — never
  a half-booted app that fails deep in a request.
- The set of required keys is declared centrally (mirrored in `.env.example`).
  Optional keys declare a default.
- Validation failures **MUST NOT** echo secret values; the message names the key,
  not its contents (Constitution §10 — never leak secrets; align with the Error
  Handler, `ARCHITECTURE.md` §6).
- This boot-time check is the configuration counterpart of "deny by default":
  the system refuses to run misconfigured rather than guessing.

## 10. System Config vs Per-Workspace Settings

These are deliberately separated; conflating them is a design error.

| Aspect | System / application config | Per-workspace settings |
|---|---|---|
| Examples | DB DSN, app URL, log level, queue driver, module defaults | tenant locale/timezone preference, branding, feature toggles per tenant, notification prefs |
| Source | env + `config/` + module `Config/` (layers 1–3) | **Settings module / database** (layer 4) |
| Scope | Whole deployment / process | One **workspace** (tenant) |
| Lifetime | Static for a deploy (cacheable, §5) | Mutable at runtime by authorized users |
| Secrets | Yes — **env only**, never committed | No secrets; tenant data, workspace-scoped |
| Access | Typed Config accessor (injected) | Read via the **Settings** module's Contract |
| Owner doc | This guide | Settings module + `WORKSPACE_MODEL.md` |

- **Per-workspace settings live in the database, tenant-isolated by
  `workspace_id`** (Constitution §5; `ARCHITECTURE.md` §8;
  `DATABASE_ARCHITECTURE.md`). They are **not** placed in `.env` or `config/`,
  and the Config loader does **not** own them.
- Where both could apply (e.g. default locale), the resolution is: **workspace
  setting (layer 4) overrides the system default (layers 1–3)** for that
  workspace's requests — consistent with the precedence model (§2, §6).
- Other code reads workspace settings through the **Settings module's published
  Contract**, never by querying its tables (cross-module rule, `ARCHITECTURE.md`
  §4). See `WORKSPACE_MODEL.md` for the workspace/settings model of record.

## 11. Illustrative Examples

> **EXAMPLE ONLY — illustrative, not source.** A `config/` file maps **env**
> values into a typed, declarative array. It contains no logic and no secrets —
> only references to env values that are supplied at boot.

```php
<?php
// EXAMPLE ONLY — config/app.php  (not source)
declare(strict_types=1);

return [
    'name'      => env('APP_NAME', 'HaHireAI'),   // default for optional key
    'env'       => env('APP_ENV', 'production'),
    'url'       => env('APP_URL'),                 // required (validated at boot)
    'timezone'  => 'UTC',                          // fixed app default
    'locale'    => env('APP_LOCALE', 'en'),        // system default; workspace may override
    'log_level' => env('LOG_LEVEL', 'info'),       // PSR-3 level (ARCHITECTURE.md §6)
];
```

A module config file (`app/Modules/Jobs/Config/jobs.php`) is the same shape,
namespaced under `modules.jobs.*` by the loader (e.g. `'max-active-postings' =>
(int) env('JOBS_MAX_ACTIVE', 100)`), declarative and secret-free.

> **EXAMPLE ONLY — access (illustrative).** System config is read via the injected,
> typed accessor with a default — never `getenv()`, a global, or an inline literal:
> `$limit = $config->int('modules.jobs.max-active-postings', 100);`. A per-workspace
> setting (layer 4) comes from the **Settings** contract, overriding the system
> default: `$locale = $settings->forWorkspace($workspaceId)->get('locale')` — not
> the Config loader, not a direct table read.

## 12. Self-Review (Phase 6 gate)

- [ ] No tunable value is a `const`/`define()`/global — all flow through the Config
      loader; `getenv()`/`$_ENV` is read only during config assembly.
- [ ] No secret appears in `config/`, module `Config/`, or the repo; secrets come
      only from env; `.env.example` lists every required key.
- [ ] Layer precedence holds: workspace/runtime > module `Config/` > `config/` >
      env default; required env vars are validated at boot with a secret-safe fail.
- [ ] System config and per-workspace settings are separated; workspace settings
      live in the Settings module/DB, tenant-isolated by `workspace_id`.
- [ ] Config access is typed and injected; the tree is read-only at runtime; the
      design permits a later config cache (layers 1–3 only).

---

### Related Documents
`PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` ·
`DIRECTORY_STANDARD.md` · `SERVICE_CONTAINER.md` · `BOOTSTRAP_FLOW.md` ·
`ROUTING_GUIDE.md` · `WORKSPACE_MODEL.md` · `DATABASE_ARCHITECTURE.md` ·
`SECURITY_GUIDE.md`
