# CODING STANDARD — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md` (§6–§9).

---

## 0. Purpose & Scope

This document is the detailed elaboration of the coding rules summarized in the
**Constitution** (`PROJECT_CONSTITUTION.md` §6 Coding Standards, §7 Naming
Conventions, §8 Folder Standards, §9 Module Standards). The Constitution states
the *law*; this document states the *how*.

**Supremacy.** If anything here conflicts with the Constitution, the Constitution
wins and this document is the artifact to be corrected (Constitution §0). Where
this document is silent, the Constitution and `ARCHITECTURE.md` govern.

**Applies to** all PHP authored for the HaHireAI platform — every module, the
Core Kernel, the Shared Kernel (`/shared`), and CLI entrypoints. Frontend assets
(TailwindCSS, Alpine.js, Vanilla JS) are governed by `UI_GUIDELINES.md`; this
document covers server-side PHP.

**Interpretation keywords.** **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
and **MAY** follow [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119), matching
the Constitution. A **MUST / MUST NOT** rule is binding; a violation is a defect
that blocks merge (Constitution §13, §14).

**No framework.** HaHireAI is built on **Native PHP 8.3+**. Laravel, Symfony,
CodeIgniter, Yii, or any other PHP framework is forbidden (Constitution §5).
Composer is permitted for dependency management and dev tooling only. Code
examples below are **EXAMPLES** for illustration; they are not source files and
MUST NOT be copied as-is into the codebase.

---

## 1. Mandatory Standards

| Standard | Rule |
|---|---|
| **PSR-12** | Extended coding style. All PHP **MUST** conform. Enforced by PHP_CodeSniffer. |
| **PSR-4** | Autoloading. Namespaces map to paths under the `HaHireAI\` root (Constitution §7). |
| **PSR-3** | Logging. Code **MUST** depend on the PSR-3 `LoggerInterface`, never on a concrete logger or `error_log()`. |
| `declare(strict_types=1);` | **MUST** be the first statement of **every** PHP file, with no exception (Constitution §5). |
| **UTF-8 / LF** | Files are UTF-8 without BOM, Unix line endings, ending with a single trailing newline. |

Additional binding rules:

- One class, interface, enum, or trait **per file**. The filename **MUST** match
  the type name.
- Namespaces follow the canon: `HaHireAI\<Module>\<Layer>` (Constitution §7).
  A type's namespace **MUST** reflect its layer (`Domain`, `Application`,
  `Infrastructure`, `Presentation`, `Contracts`).
- `strict_types` means call sites pass exactly typed arguments. Implicit scalar
  coercion is a defect, not a convenience.

> **EXAMPLE — file preamble (illustrative only):**
> ```php
> <?php
>
> declare(strict_types=1);
>
> namespace HaHireAI\Jobs\Domain;
> ```

---

## 2. Type Safety

Strong typing is non-negotiable (Constitution §6).

- Every property, parameter, and return value **MUST** be typed. `void` and
  `never` **MUST** be used where applicable.
- Prefer the **narrowest** type. Use union and nullable types deliberately;
  `?Foo` is preferred over `Foo|null` for a single nullable type.
- `readonly` **MUST** be used for properties that do not change after
  construction. Value objects (§3) are `readonly` end to end.
- `mixed` is **forbidden** in domain and application code. It **MAY** appear only
  at true serialization boundaries (e.g. decoding external JSON) and **MUST** be
  narrowed immediately to a typed shape.
- Public APIs (anything in `Contracts/`) **MUST** be fully typed; PHPStan/Psalm
  generics annotations **SHOULD** be added where collections are returned.
- Avoid "stringly typed" code: identifiers, statuses, and enumerations get value
  objects or enums (§3), not bare `string` / `int`.

> **EXAMPLE — typed, readonly, narrow (illustrative only):**
> ```php
> public function moveToStage(
>     ApplicationId $applicationId,
>     PipelineStageId $stageId,
> ): MoveResult {
>     // ...
> }
> ```

---

## 3. Enums, Value Objects & Named Constructors

- **Enums** **MUST** represent every fixed set of states or kinds — e.g.
  `JobStatus`, `ApplicationStatus`, `InterviewKind`. Backed enums (`: string`)
  **SHOULD** be used where the value is persisted, with the stored value stable
  across releases. Branching on raw strings instead of an enum is a defect.
- **Value objects** model concepts with identity-by-value (IDs, money, email,
  date ranges). They **MUST** be immutable (`readonly`), validate in their
  constructor, and expose behavior — not just getters (avoid anemic objects, §10).
- **ULID identifiers** are value objects (Constitution §7 PK rules;
  `DATABASE_ARCHITECTURE.md`). Use the shared ULID type from `/shared`; do not
  pass raw strings as identifiers across boundaries.
- **Named constructors** (static factory methods such as `fromString()`,
  `generate()`, `fromTimestamp()`) **SHOULD** be preferred over public
  constructors with ambiguous arguments. Keep the language constructor minimal
  and private/protected where a named constructor is the intended entry point.

> **EXAMPLE — enum + value object (illustrative only):**
> ```php
> enum ApplicationStatus: string
> {
>     case Submitted = 'submitted';
>     case Screening = 'screening';
>     case Hired     = 'hired';
>     case Rejected  = 'rejected';
> }
>
> final readonly class EmailAddress
> {
>     private function __construct(public string $value) {}
>
>     public static function fromString(string $raw): self
>     {
>         $value = strtolower(trim($raw));
>         if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
>             throw new InvalidEmailAddress($raw);
>         }
>         return new self($value);
>     }
> }
> ```

---

## 4. Naming Conventions

This mirrors **Constitution §7** (authoritative). The table below is a working
subset; for the full canon, see the Constitution.

| Element | Convention | Example |
|---|---|---|
| Namespace root | `HaHireAI\` | `HaHireAI\Jobs\Domain\Job` |
| Module namespace | `HaHireAI\<Module>\<Layer>` | `HaHireAI\Applications\Application` |
| Public contract (interface) | PascalCase, **no suffix**, in `…\Contracts\` | `HaHireAI\Jobs\Contracts\JobService` |
| Concrete implementation | Descriptive, **technology-prefixed** | `MySqlJobRepository`, `DefaultJobService` |
| Class | PascalCase | `ApplicationPipeline` |
| Method / function | camelCase | `moveToStage()` |
| Variable / property | camelCase | `$workspaceId` |
| Constant / enum case | UPPER_SNAKE_CASE / PascalCase | `MAX_UPLOAD_BYTES`, `JobStatus::Published` |
| Enum type | PascalCase | `ApplicationStatus` |
| DB table | snake_case, **plural** | `workspace_members` |
| DB column | snake_case | `created_at` |
| Permission key | `resource.action` (lowercase, dot) | `job.create`, `interview.ai.run` |
| Domain event | `<module>.<entity>.<event>` (past tense) | `applications.application.submitted` |

Binding clarifications:

- **Interfaces in `Contracts/` carry no suffix** — no `Interface`, no `I`
  prefix. The implementation is the one that gets a descriptive,
  technology-prefixed name. A module's `Contracts` namespace **is** its public
  API (Constitution §7; `ARCHITECTURE.md` §3).
- Repository implementations are named for their backing technology
  (`MySqlJobRepository`), never generically (`JobRepositoryImpl` is forbidden).
- Permission keys referenced in code **MUST** match the catalog exactly
  (`PERMISSION_MODEL.md`, `PERMISSION_CATALOG.md`); never invent ad-hoc keys.

---

## 5. Class Design

- **Single Responsibility.** Each class has one reason to change (Constitution
  §9). A class that orchestrates, persists, and renders is three classes.
- **Small classes, small methods.** Prefer short, named methods over long ones.
  A method that needs scrolling to read **SHOULD** be decomposed.
- **Composition over inheritance.** Inheritance is reserved for genuine
  is-a relationships. Deep hierarchies are forbidden; share behavior via
  collaborators, traits (sparingly), or shared services.
- **Constructor injection only.** All collaborators are declared as constructor
  parameters and stored in `readonly` properties. Setter injection and property
  injection are forbidden in business logic.
- **No global state.** No `global`, no mutable `static` registries, no
  singletons reached implicitly. The Service Container owns lifetimes
  (`ARCHITECTURE.md` §6; `SERVICE_CONTAINER.md`).
- **`final` by default.** Concrete classes **SHOULD** be `final` unless they are
  explicitly designed for extension. Extensibility flows through interfaces, not
  open inheritance.
- Constructors do **no work** beyond assignment and cheap validation — no I/O, no
  queries, no side effects.

---

## 6. Dependency Discipline — No Facades, No Service Locator

This section is a hard architectural rule (Constitution §4, §6;
`ARCHITECTURE.md` §6).

- **No facades.** Static "facade" access to services (framework-style) is
  forbidden. There is no framework and there are no facades.
- **No service locator inside business logic.** Domain and Application code
  **MUST NOT** pull dependencies from the container, a registry, or a global
  accessor. Dependencies are **injected** via the constructor and resolved by the
  container only at the composition root (service providers / module bootstrap).
- **Depend on contracts, not implementations.** Type-hint the interface from
  `Contracts/`; the container binds interface → implementation at boot
  (`ARCHITECTURE.md` §5).
- **`static` is reserved for true, stateless utilities** (pure functions with no
  dependencies and no state) and named constructors (§3). Stateful statics,
  static service access, and static caches are forbidden.
- Cross-cutting capabilities (Auth, Permissions, Files, Notifications, Search,
  AI, Logging, Cache, Validation) are consumed via their **shared-service
  contracts**, never re-implemented or reached statically (Constitution §4;
  `MODULES.md` §4).

> **EXAMPLE — constructor injection of contracts (illustrative only):**
> ```php
> final readonly class DefaultJobService implements JobService
> {
>     public function __construct(
>         private JobRepository $jobs,          // Contracts interface
>         private PermissionChecker $permissions, // shared-service contract
>         private LoggerInterface $logger,        // PSR-3
>     ) {}
> }
> ```

---

## 7. Error Handling

Per the Constitution (§6): control flow via exceptions is reserved for
exceptional cases; expected domain failures use explicit result/exception types.

- **Exceptions for the exceptional.** Programming errors, broken invariants, and
  infrastructure failures throw. Use specific exception classes, never bare
  `\Exception` or `\RuntimeException` for domain meaning.
- **Expected domain failures are modeled explicitly.** Outcomes a caller is
  expected to handle (validation failed, stage transition not allowed, quota
  exceeded) **SHOULD** use a typed **Result** object (from `/shared`) or a
  narrowly-typed domain exception that the caller catches deliberately. Do not
  use generic exceptions for ordinary business outcomes.
- **Exception hierarchy.** Each module defines its own exception types, extending
  a shared base where useful. Domain exceptions live in `Domain/`, never leak
  Infrastructure details (e.g. wrap PDO exceptions, do not rethrow them upward).
- **Never swallow exceptions.** Empty `catch` blocks are forbidden. A catch must
  handle, translate, or log-and-rethrow — never silently discard.
- **Never leak stack traces.** The global Error Handler (`ARCHITECTURE.md` §6)
  renders detailed diagnostics in development and **generic, safe** responses in
  production. Stack traces, SQL, file paths, and secrets **MUST NOT** reach an
  end user or an API client. API errors follow the envelope in
  `API_GUIDELINES.md`.
- **Log with context, not secrets.** Use the PSR-3 logger with structured
  context. Never log passwords, tokens, full PII, or raw request bodies.

---

## 8. Security-Adjacent Coding Rules

These reinforce `SECURITY_GUIDE.md` and Constitution §10 at the code level. They
are binding here because they are routinely violated in code, not config.

- **Prepared statements only.** Every SQL statement uses bound parameters.
  String-concatenated or interpolated SQL is **forbidden** without exception
  (Constitution §5, §6). Even "constant" values go through binding or an allow-list.
- **Tenant guard always.** Every workspace-scoped query **MUST** be filtered by
  `workspace_id` at the Infrastructure layer (Constitution §5;
  `ARCHITECTURE.md` §8). Omitting the tenant filter is a critical defect, not a
  style issue.
- **Permission check at the boundary.** Every protected action passes a
  permission check (by key) at the Application boundary before doing work —
  deny by default (`PERMISSION_MODEL.md` §5). Checks reference permission
  **keys**, never role names.
- **Escape output on render.** All dynamic output is escaped at the point of
  rendering for its context (HTML, attribute, JS, URL). Templates never emit raw
  user input. See `UI_GUIDELINES.md`.
- **Validate all input.** Untrusted input is validated and normalized at the
  edge via the shared Validation service before it reaches domain logic. Domain
  objects additionally enforce their own invariants (§3).
- **No secrets in code.** Configuration and secrets come from the environment
  (Constitution §5, §10). No credentials, keys, or tokens in source or fixtures.

---

## 9. Comments & Documentation

Self-documenting code is the goal; comments explain **why**, not **what**.

- **Docblocks are required** on: every public `Contracts/` member, anything with
  non-obvious intent, and wherever PHPStan/Psalm needs generic or array-shape
  annotations. A docblock that merely restates the signature adds no value and
  **SHOULD** be omitted.
- **Names over comments.** Prefer a well-named method or variable to an
  explanatory comment. If a comment is needed to explain *what* the code does,
  refactor first.
- **No dead code.** Commented-out code, unreachable branches, and unused
  imports/parameters are defects — delete them. Version control is the history.
- **No TODO debt in merged code.** A `TODO`/`FIXME` **MUST** reference a tracked
  issue; orphan TODOs are not merged.
- **English only** in code, comments, and identifiers (Constitution §12).
  Product UI strings are bilingual AR/EN via the i18n layer, never hard-coded.

---

## 10. Anti-Patterns (Forbidden)

The following are **defects**, listed so reviewers can name them directly:

- **God classes / god services.** Classes that accumulate unrelated
  responsibilities. Split by responsibility (§5).
- **Anemic domain leaking into procedural code.** Value objects and entities
  that are bags of getters/setters while logic accumulates in "manager"/"helper"
  scripts. Behavior belongs **with** the data (§3).
- **Cross-module internals.** Importing or instantiating another module's
  `Domain`, `Application`, `Infrastructure`, or `Persistence` classes, or reading
  its tables. Modules communicate **only** via `Contracts/`, events, or shared
  services (Constitution §4; `ARCHITECTURE.md` §4). This is a critical defect.
- **Fat controllers.** Business logic in Presentation. Controllers parse input,
  invoke an Application use case, and shape a response — nothing more. Domain and
  application logic live in **Application/Domain**, never in Presentation
  (`ARCHITECTURE.md` §2).
- **Layer skipping.** Presentation running raw SQL, or any higher layer reaching
  past Application into Persistence (Constitution §4; `ARCHITECTURE.md` §2).
- **Service location / facades / global state.** See §6.
- **Stringly-typed states.** Branching on raw strings instead of enums (§3).
- **Silent failure.** Swallowed exceptions, ignored return values, empty catches
  (§7).
- **Unbounded queries.** List access without pagination (Constitution §11);
  N+1 query patterns.
- **Hard-coded roles.** Any branch on a role name (Constitution §15;
  `PERMISSION_MODEL.md` §1).

---

## 11. Tooling Gates

Every change **MUST** pass the automated gates before merge (Constitution §13,
§14). Detailed configuration and commands live in `TESTING_GUIDE.md`; CI wiring
in `DEPLOYMENT_GUIDE.md`.

| Gate | Tool | Rule |
|---|---|---|
| Coding style | **PHP_CodeSniffer** | PSR-12 ruleset; zero violations. |
| Static analysis | **PHPStan and/or Psalm** | Maximum practical level; no new baseline entries; `mixed` flagged. |
| Dependency security | **`composer audit`** | No known-vulnerable dependencies. |
| Tests | **PHPUnit** | Green; domain coverage **≥ 80%** (Constitution §13). |

Rules:

- A **red gate blocks merge** (Constitution §13). Gates are not advisory.
- Static-analysis baselines **MUST NOT** grow to hide new issues; reducing the
  baseline is encouraged.
- These checks run locally (developer responsibility) and in CI (enforcement).
- Production builds use **OPcache** and the **optimized Composer autoloader**
  (Constitution §11).

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`PERMISSION_MODEL.md` · `SECURITY_GUIDE.md` · `API_GUIDELINES.md` ·
`TESTING_GUIDE.md` · `DATABASE_ARCHITECTURE.md` · `SERVICE_CONTAINER.md` ·
`UI_GUIDELINES.md`
