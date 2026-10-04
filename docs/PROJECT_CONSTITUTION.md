# PROJECT CONSTITUTION — HaHireAI

> **Status:** Adopted · **Version:** 1.1.0 · **Last updated:** 2026-06-27
> **Applies to:** The entire HaHireAI platform, all modules, all contributors (human or AI).

---

## 0. Preamble

This document is the **supreme reference** of the HaHireAI project. It defines the
non-negotiable rules that govern how the system is designed, built, documented,
secured, tested, and released.

**Supremacy clause.** If any document, code comment, pull request, agent
instruction, or design decision conflicts with this Constitution, **this
Constitution wins**. The conflicting artifact must be corrected — not the
Constitution — unless the Constitution is formally amended (see §16).

**Interpretation keywords.** The words **MUST**, **MUST NOT**, **SHOULD**,
**SHOULD NOT**, and **MAY** are used per [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).
A rule written with **MUST / MUST NOT** is binding. A violation is a defect.

**Scope of this document.** This Constitution states the *law*. The detailed
*how* lives in dedicated documents (`CODING_STANDARD.md`, `SECURITY_GUIDE.md`,
`ARCHITECTURE.md`, etc.). Those documents elaborate; they never contradict.

---

## 1. Vision

HaHireAI is an **AI-native, multi-tenant hiring operations platform** built to be
operated by thousands of companies simultaneously. It is engineered to the
standard of products like **Slack, Notion, GitHub, Linear, and the Stripe
Dashboard** — a single coherent product, not a CRUD application and not a job
board.

We believe hiring is an **operational discipline**, not a sequence of forms.
HaHireAI treats every job, application, candidate, and interview as part of one
intelligent, observable system where **AI is the engine** that powers every
recruitment workflow.

## 2. Mission

To give any organization — from a 3-person startup to a 50,000-person enterprise —
a workspace where they can **source, evaluate, interview, and hire** talent with
the speed and confidence that only an AI-first, evidence-driven system can
provide, while giving every individual a **single identity** that follows them
whether they are hiring, being hired, or working.

## 3. Core Principles

1. **System over pages.** We design business domains, not screens. The UI is a
   projection of the system, never its definition.
2. **AI is an engine, not a feature.** Every recruitment workflow MUST be able to
   route through the central AI Engine. AI is core infrastructure (see
   `AI_ENGINE.md`).
3. **One identity, many contexts.** There is exactly one human account type:
   `User`. "Candidate", "Recruiter", "Interviewer", "Employee", etc. are
   *contexts and permissions*, never account types (see `USER_MODEL.md`).
4. **Permissions, not roles.** Authorization is built on fine-grained
   permissions. Roles are merely named bundles of permissions and MUST NOT be
   hard-coded (see `PERMISSION_MODEL.md`).
5. **Workspace is the tenant boundary.** All business data is isolated per
   workspace. Cross-workspace data leakage is a critical security defect (see
   `WORKSPACE_MODEL.md`).
6. **Modular monolith.** The system is one deployable, internally partitioned
   into autonomous modules that communicate only through explicit contracts and
   events (see `ARCHITECTURE.md`).
7. **Security and privacy by design.** Security is designed before the first line
   of code, not bolted on afterward.
8. **Documentation first.** No module is implemented before its documentation is
   written and approved.
9. **Evidence and auditability.** Significant actions are observable and
   auditable. The system never relies on self-reported truth where it can
   observe the truth.
10. **Built for scale and change.** A new module MUST be addable without
    redesigning the database, the navigation, or the permission model.

## 4. Architecture Rules

- The system **MUST** be a **Modular Monolith**. Traditional flat MVC is
  forbidden as the top-level structure.
- The system **MUST** be layered: **Presentation → Application → Domain →
  Infrastructure → Persistence**. A higher layer **MUST NOT** be called by a
  lower layer. Layers **MUST NOT** be skipped in a way that violates the
  dependency rule (see `ARCHITECTURE.md`).
- A module **MUST NOT** call another module's internal classes directly. Cross-
  module communication happens **only** through: published **Contracts**
  (interfaces), the **Event Bus**, or shared **Services**.
- A module **MUST NOT** read or write another module's database tables directly.
- **Circular dependencies between modules are forbidden.**
- The **only** web-exposed directory is `/public` via a single front controller
  (`/public/index.php`).
- Cross-cutting capabilities (Authentication, Permissions, Files, Notifications,
  Search, AI Provider Layer, Logging, Cache, Validation) **MUST** be provided as
  shared services and **MUST NOT** be re-implemented inside individual modules.

## 5. Development Rules

- **No framework.** The platform is built on **Native PHP 8.3+**. Laravel,
  Symfony, CodeIgniter, Yii, or any other PHP framework is **forbidden**.
  Composer is allowed for dependency management and dev tooling only.
- **Mandatory stack:** Native PHP 8.3+, MySQL 8+, TailwindCSS, Alpine.js (UI only,
  when needed), Vanilla JavaScript, Composer (dependencies only).
- `declare(strict_types=1);` **MUST** be present in every PHP file.
- Every change **MUST** comply with `CODING_STANDARD.md` and pass static
  analysis, coding-standard, and test gates before merge (see §13, §14).
- **Documentation precedes code.** A module's specification in
  `FEATURE_SPECIFICATIONS/` MUST exist and be approved before implementation.
- Secrets **MUST NOT** be committed. Configuration comes from environment.
- Every workspace-scoped data access **MUST** be tenant-guarded (filtered by
  `workspace_id`). This is non-negotiable.

## 6. Coding Standards (summary)

Full detail in `CODING_STANDARD.md`. Binding essentials:

- **PSR-12** code style, **PSR-4** autoloading, **PSR-3** logging interface.
- Strict types everywhere; typed properties, parameters, and return types.
- Prefer **immutable value objects** and **enums** for fixed sets of states.
- No global mutable state; dependencies are injected via the container.
- Use **prepared statements only** — string-concatenated SQL is forbidden.
- Expected domain failures use explicit result/exception types; control flow via
  exceptions is reserved for exceptional cases.

## 7. Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Namespace root | `HaHireAI\` | `HaHireAI\Jobs\Domain\Job` |
| Module namespace | `HaHireAI\<Module>\<Layer>` | `HaHireAI\Applications\Application` |
| Public contract (interface) | PascalCase, **no suffix**, in `…\Contracts\` | `HaHireAI\Jobs\Contracts\JobService` |
| Concrete implementation | Descriptive, technology-prefixed | `MySqlJobRepository`, `DefaultJobService` |
| Class | PascalCase | `ApplicationPipeline` |
| Method / function | camelCase | `moveToStage()` |
| Variable / property | camelCase | `$workspaceId` |
| Constant / enum case | UPPER_SNAKE_CASE / PascalCase | `MAX_UPLOAD_BYTES`, `JobStatus::Published` |
| Enum type | PascalCase | `ApplicationStatus` |
| DB table | snake_case, **plural** | `workspace_members` |
| DB column | snake_case | `created_at` |
| Primary key | `id` | `id` |
| Foreign key | `<entity>_id` | `workspace_id`, `job_id` |
| Boolean column | `is_` / `has_` prefix | `is_active`, `has_offer` |
| Permission key | `resource.action` (lowercase, dot) | `job.create`, `interview.ai.run` |
| System permission key | `system.<area>.<action>` | `system.workspaces.manage` |
| Domain event | `<module>.<entity>.<event>` (past tense) | `applications.application.submitted` |
| Route / URL | kebab-case | `/jobs/active-postings` |
| Doc file | `UPPER_SNAKE.md` in `/docs` | `DOMAIN_MODEL.md` |

**One public contract namespace per module.** A module's public API *is* its
`Contracts` namespace. Anything outside `Contracts` is internal and MUST NOT be
referenced by other modules.

## 8. Folder Standards

Canonical top-level layout (authoritative detail in `PROJECT_STRUCTURE.md`):

```
/app          Application code:
  /Core         the Core Kernel (container, router, config/env, logger, errors, registry, health)
  /Modules      all business modules (the modular monolith)
  /Shared       the Shared Kernel (ULID, Result, Clock, base contracts, helpers)
  /Infrastructure  technical drivers (DB/cache/queue/mail/fs) behind contracts
  /Services     cross-module shared-service implementations
  /Providers    service providers (register bindings at boot)
  /Contracts    cross-cutting interfaces shared platform-wide
  /Support      stateless helpers/utilities
/bootstrap    Boot a request into a kernel (no domain logic)
/config       Configuration files (no secrets)
/database     Global migration runner, global migrations & seeds
/public       Web root — single front controller (index.php) + built assets
/resources    Shared layouts/views, JS, CSS (Tailwind source), lang/ (ar, en)
/routes       Global route registration (aggregates module routes)
/storage      Logs, cache, compiled views, uploads (git-ignored)
/tests        Cross-module / end-to-end tests
/docs         Project documentation
/vendor       Composer dependencies (git-ignored)
composer.json
.env.example
```

Per-module layout (binding — see `ARCHITECTURE.md` §Module Anatomy):

```
/app/Modules/<Module>/
  module.php          Manifest: name, version, dependencies, permissions, events, routes, enabled-by
  Domain/             Entities, value objects, domain services, domain events, domain contracts
  Application/        Use cases, command/query handlers, application services
  Infrastructure/     Repository implementations, external adapters
  Presentation/       Controllers, view-models, CLI commands
  Contracts/          Public interfaces exposed to other modules
  Resources/          Module views (views/) and assets (assets/)
  Routes/             Route definitions
  Config/             Module configuration
  Permissions/        Permission definitions
  Policies/           Access policies
  Events/             Event definitions and listeners
  Database/           Module migrations and seeds
  Tests/              Unit, Integration, Feature tests
```

## 9. Module Standards

- Each module **MUST** have a **single responsibility** and own its data.
- Each module **MUST** ship: documentation, services, repositories, views,
  routes, assets, configuration, permissions, policies, events, and tests.
- A module **MUST** declare its dependencies in `module.php`. Undeclared
  dependencies are forbidden.
- A module **MUST** expose behavior to others only through `Contracts/` and
  events. It **MUST NOT** expose entities or repositories directly.
- A module **MUST** be removable/disable-able without breaking unrelated modules
  (graceful degradation when an optional dependency is disabled).
- Adding a new module **MUST NOT** require changing the database design, the
  navigation engine, or the permission engine — only adding to them.

## 10. Security Standards (summary)

Full detail in `SECURITY_GUIDE.md`. Binding essentials:

- **Deny by default.** Every action passes a permission check; absence of a
  granted permission means denied.
- **Tenant isolation is absolute.** No workspace can read another workspace's
  data. Every workspace-scoped query is filtered by `workspace_id`.
- **Two system account levels only:** `System Owner` and `User`. No other
  account type exists.
- Passwords hashed with **Argon2id**. Sessions are secure, `HttpOnly`,
  `SameSite`. **CSRF tokens** on all state-changing requests.
- **Prepared statements only.** All output is escaped on render.
- File uploads are validated, stored outside the web root, and scanned.
- PII access is permission-gated, audited, and encrypted at rest where required.
- Secrets live in the environment, never in the repository. HTTPS/HSTS enforced.

## 11. Performance Standards

- Every list endpoint **MUST** be paginated. Unbounded queries are forbidden.
- **No N+1 queries.** Data access is batched or eager-loaded deliberately.
- Heavy or AI work **MUST** run asynchronously via the queue; web requests stay
  responsive.
- Indexing is mandatory and justified (see `INDEXING_GUIDE.md`).
- A defined cache layer is used for hot reads; caches are explicitly invalidated.
- **Performance budgets:** server-rendered page p95 **< 300 ms**; internal API
  p95 **< 200 ms** (excluding async AI work). OPcache and optimized autoloader
  enabled in production.

## 12. Documentation Policy

- `/docs` is the **single source of truth**. The Constitution is supreme within
  it.
- **Docs-first:** no module code before its specification is approved.
- Significant decisions are recorded as **ADRs** in `/docs/adr/`.
- `CHANGELOG.md` follows *Keep a Changelog* + SemVer.
- Every document carries a status, version, and last-updated date and is kept in
  sync with reality. Stale docs are defects.
- Documentation is written in **English** (engineering standard); product UI is
  bilingual **AR/EN** (see `UI_GUIDELINES.md`).

## 13. Testing Policy

Full detail in `TESTING_GUIDE.md`. Binding essentials:

- A **test pyramid**: unit (domain) > integration (module + DB) > feature/e2e.
- **PHPUnit** is the test runner (a dev dependency, not a framework).
- Domain-layer logic **MUST** reach **≥ 80%** coverage.
- CI **MUST** run: tests, static analysis (PHPStan/Psalm), coding standard
  (PHP_CodeSniffer), and `composer audit`. A red gate blocks merge.
- Each module owns its tests under `Tests/`.

## 14. Release Policy

- **Semantic Versioning** (`MAJOR.MINOR.PATCH`).
- **Trunk-based** development: short-lived feature branches → pull request →
  green CI → review → merge.
- **Conventional Commits** for commit messages.
- Environments flow **local → staging → production** (see `DEPLOYMENT_GUIDE.md`).
- Database migrations are forward-only and gated; every release updates
  `CHANGELOG.md`.
- Progressive rollout via feature flags / enabled-modules where risk warrants.

## 15. Non-Negotiables (Golden Rules)

1. No framework. Native PHP only.
2. No hard-coded roles. Permissions drive everything.
3. One `User` identity. Contexts, not account types.
4. Workspace isolation is absolute.
5. Modules talk through contracts and events — never internals or shared tables.
6. AI is a central engine every workflow can route through.
7. Documentation precedes implementation.

## 16. Amendment Process

This Constitution may only change through a deliberate amendment:

1. Open an ADR in `/docs/adr/` proposing the change and its rationale.
2. Bump this document's version (SemVer: breaking rule change = MAJOR).
3. Update every document that the change affects, in the same pull request, so
   the docs remain internally consistent.
4. Record the amendment in `CHANGELOG.md`.

---

### Related Documents

`SYSTEM_OVERVIEW.md` · `DOMAIN_MODEL.md` · `ARCHITECTURE.md` · `SYSTEM_BLUEPRINT.md` ·
`MODULES.md` · `USER_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`DATABASE_ARCHITECTURE.md` · `SECURITY_GUIDE.md` · `CODING_STANDARD.md` ·
`AI_ENGINE.md` · `UI_GUIDELINES.md`
