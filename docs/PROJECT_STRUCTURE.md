# PROJECT STRUCTURE — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`.
> **Reconciliation:** This document is the authoritative project layout. It
> refines `PROJECT_CONSTITUTION.md` §8; that section has been updated to match.
> See `adr/0001-project-structure.md`.

---

## 1. Principles

- A **new developer must understand the project in under an hour** from this doc.
- Every folder has **exactly one reason to exist**; no overlapping
  responsibilities.
- The structure must let a **new module be added without touching existing
  folders** (only adding under `/app/Modules`).
- Only `/public` is web-exposed (single front controller).

## 2. Top-Level Layout (authoritative)

```
/                         project root
├── app/                  application code
│   ├── Core/             the Core Kernel (Phase 7): kernel, container, router,
│   │                     dispatcher, config & env loaders, event dispatcher,
│   │                     logger, error handler, module registry, health checker
│   ├── Modules/          all business modules (the modular monolith)
│   ├── Shared/           the Shared Kernel: ULID, Result, Clock, base contracts,
│   │                     value objects, pure helpers (no module/business logic)
│   ├── Infrastructure/   app-level infrastructure: DB connection manager, cache
│   │                     driver, queue driver, mail transport, filesystem driver
│   ├── Services/         app-level implementations of shared services that span
│   │                     modules (consumed via Contracts)
│   ├── Providers/        service providers (register bindings/services at boot)
│   ├── Contracts/        global/cross-cutting interfaces not owned by one module
│   └── Support/          small framework-agnostic helpers/utilities
├── bootstrap/            app bootstrap (autoload wiring, kernel construction,
│                         cache bootstrap) invoked by public/index.php
├── config/               configuration files (loaded by the Config loader)
├── database/             global migration runner, global migrations & seeds
├── public/               web root — single front controller (index.php) + built assets
│   ├── index.php
│   └── assets/
├── resources/            shared layouts/views, JS, CSS (Tailwind source), lang/ (ar, en)
├── routes/               global route registration (aggregates module routes)
├── storage/              logs, cache, compiled views, uploads (git-ignored)
├── tests/                cross-module / end-to-end tests
├── docs/                 project documentation (this directory)
├── vendor/               Composer dependencies (git-ignored)
├── composer.json
└── .env.example
```

> **CLI entrypoints** live in `bootstrap/` (a `console` entry) or `app/Support`;
> the project ships no framework-style global binaries. The web entrypoint is the
> single `public/index.php` front controller.

## 3. Folder Responsibilities (one reason each)

| Folder | Single responsibility |
|---|---|
| `app/Core` | The bespoke Core Kernel runtime (no business logic). |
| `app/Modules` | Autonomous business modules; the only place features live. |
| `app/Shared` | The Shared Kernel — primitives reused everywhere, zero business logic. |
| `app/Infrastructure` | Concrete technical drivers (DB/cache/queue/mail/fs) behind contracts. |
| `app/Services` | Cross-module shared-service implementations. |
| `app/Providers` | Registration of bindings/services into the container. |
| `app/Contracts` | Cross-cutting interfaces shared platform-wide. |
| `app/Support` | Stateless helpers/utilities. |
| `bootstrap` | Turn a request into a booted kernel; nothing domain-specific. |
| `config` | Declarative configuration only (no logic, no secrets). |
| `database` | Global migration engine + global migrations/seeds. |
| `public` | The only web-exposed dir; front controller + built assets. |
| `resources` | Shared views/layouts, JS/CSS source, localization (ar/en). |
| `routes` | Aggregate and register routes (delegates to module route files). |
| `storage` | Runtime artifacts (logs/cache/compiled/uploads); git-ignored. |
| `tests` | Cross-module and end-to-end tests (module tests live in the module). |
| `docs` | The canonical documentation. |

## 4. Module Internal Structure (every module identical)

Each module under `app/Modules/<Module>/` uses the same DDD-layered layout
(`ARCHITECTURE.md` §3). It **MUST NOT** deviate.

```
app/Modules/<Module>/
├── module.php          Manifest: name, version, dependencies, permissions, events, routes, enabled-by
├── Domain/             Entities, value objects, domain services, domain events, internal contracts
├── Application/        Use cases, command/query handlers, application services
├── Infrastructure/     Repository implementations, adapters (use app/Infrastructure drivers)
├── Presentation/       Controllers, view-models, CLI commands
├── Contracts/          PUBLIC interfaces exposed to other modules (the module's API)
├── Routes/             Route definitions (registered via /routes)
├── Resources/          views/  and  assets/
├── Config/             Module configuration
├── Permissions/        Permission definitions (feed the catalog)
├── Policies/           Access policies
├── Events/             Event definitions and Listeners
├── Database/           Module migrations and seeds
└── Tests/              Unit, Integration, Feature
```

### 4.1 Mapping common artifact names → layers

To avoid ambiguity, the conventional artifacts map to layers as follows:

| Artifact | Lives in |
|---|---|
| Controllers | `Presentation/` |
| Services (application) | `Application/` |
| Domain services | `Domain/` |
| Repositories (implementations) | `Infrastructure/` |
| Repository interfaces | `Domain/` (internal) or `Contracts/` (if public) |
| Views | `Resources/views/` |
| Assets | `Resources/assets/` |
| Routes | `Routes/` |
| Policies | `Policies/` |
| Permissions | `Permissions/` |
| Events & Listeners | `Events/` |
| Tests | `Tests/` |
| Documentation | `docs/FEATURE_SPECIFICATIONS/<Module>.md` |

## 5. Where things are NOT allowed

- No PHP that executes outside the front controller / CLI bootstrap.
- No module under any path other than `app/Modules/`.
- No business logic in `app/Core`, `app/Shared`, `app/Support`, or `bootstrap`.
- No secrets in `config/` or the repository (env only).
- No cross-module access to another module's `Domain/`, `Infrastructure/`, or
  tables — only its `Contracts/` and events.

## 6. Naming

All folders, files, and namespaces follow `DIRECTORY_STANDARD.md` and
`PROJECT_CONSTITUTION.md` §7. Namespaces mirror the path:
`HaHireAI\Modules\<Module>\<Layer>\…`, `HaHireAI\Core\…`, `HaHireAI\Shared\…`.

## 7. Self-Review (Phase 6 gate)

- [ ] A new developer can navigate the tree and place any file correctly.
- [ ] Adding a module touches only `app/Modules/` (+ its manifest registration).
- [ ] No folder has an ambiguous or overlapping responsibility.
- [ ] Only `public/` is web-exposed.
- [ ] The layout matches `PROJECT_CONSTITUTION.md` §8 and `ARCHITECTURE.md` §3.

---

### Related Documents
`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `DIRECTORY_STANDARD.md` ·
`BOOTSTRAP_FLOW.md` · `SERVICE_CONTAINER.md` · `ROUTING_GUIDE.md` ·
`CONFIGURATION_GUIDE.md` · `adr/0001-project-structure.md`
