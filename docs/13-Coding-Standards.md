# Coding Standards

> The enforceable engineering standard every Nizam module, package, and contribution must follow — the rulebook later phases will lint, review, and gate against.

**Status: Approved (Phase 1) | Version: 1.0.0 | Last updated: 2026-07-01 | Owner: Architecture (Nizam Core)**

---

## 0. How to Read This Document

This document defines standards. Standards are **normative**: the words **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, and **MAY** are used in the RFC 2119 sense. A "MUST" is a merge blocker; a "SHOULD" is a review default that requires a written justification to violate.

This is a documentation-only phase. Every code snippet below is tiny and marked **`illustrative — convention only`**. No snippet is product logic; each exists solely to fix a naming or shape convention so later phases have one unambiguous target.

The constitution requirement is absolute: **every module ships a `README.md`, documentation is updated in the same task that changes behavior, and every class carries a PHPDoc block (constitution rule 15).** See §17 and the [Project Constitution](../README.md).

---

## 1. Language & Runtime

Nizam is **native PHP on PSR standards** — framework-agnostic. There is **no full-stack framework** (no Laravel, no Symfony full-stack). Selected Composer libraries are used **behind ports**; the domain layer is pure PHP with zero library dependencies.

| Concern | Standard | Enforcement |
|---------|----------|-------------|
| Language | **PHP 8.3+** only. No feature relying on a newer minor without a raised platform floor. | `composer.json` `"require": { "php": ">=8.3" }`, `platform.php` config, CI matrix |
| Strict types | **`declare(strict_types=1);`** is the first statement in **every** PHP file. | PHP-CS-Fixer `declare_strict_types`, PHPStan, CI check |
| Framework | **Framework-agnostic native PHP.** No full-stack framework lock-in. Libraries sit behind ports (§10). | Dependency review; PHPStan disallowed-namespace rules |
| Standards baseline | **PSR-1** (basic), **PSR-4** (autoloading), **PSR-3** (logging), **PSR-7** (HTTP messages), **PSR-15** (middleware/handlers), **PSR-11** (container), **PSR-12 / PER** (style). | PHP_CodeSniffer (PSR-12/PER ruleset), CI |
| Type coverage | Every function/method has **parameter, return, and property type declarations**. Use `never`/`void`/union/intersection/enums as precise. Avoid `mixed` except at genuine deserialization edges. | PHPStan max level `treatPhpDocTypesAsCertain`, Psalm |
| `mixed` | **SHOULD NOT** appear in committed code. Narrow with type guards, generics via PHPDoc (`@param list<Foo>`), or a typed model. Each use requires a `@psalm-suppress`/baseline entry with a linked issue. | PHPStan/Psalm baseline is append-only and reviewed |
| Suppressing analysis | `@phpstan-ignore`/`@psalm-suppress`/`/** @phpstan-ignore-next-line */` require an inline reason and a linked issue. | Review default; baseline diff gate |
| Nullability | No silencing the analyzer with `??` fallbacks that hide bugs, and no unchecked `?->`-then-assume. Prove non-null via control flow or a `Result`/guard. | PHPStan strict rules |

**Analyzer/config flags that MUST be on:** PHPStan `level: max` with `strict-rules` and `bleedingEdge`; `checkMissingIterableValueType`, `checkGenericClassInNonGenericObjectType`, `reportUnmatchedIgnoredErrors: true`; Psalm `errorLevel="1"` with `findUnusedCode` and `findUnusedBaselineEntry`.

```php
// illustrative — convention only (composer.json + file header excerpt)
{ "require": { "php": ">=8.3" }, "autoload": { "psr-4": { "Nizam\\": "src/" } } }
// every file starts:
<?php
declare(strict_types=1);
namespace Nizam\Agents\Domain;
```

---

## 2. Naming Conventions

Naming is **FIXED** by the canon (PSR). It is not a matter of taste.

| Scope | Case | Example |
|-------|------|---------|
| Database identifiers (tables, columns, indexes, constraints) | `snake_case` | `agent_run`, `tenant_id`, `created_at` |
| Classes, Interfaces, Traits, Enums, aggregates, entities, VOs | `PascalCase` | `AgentRun`, `ToolManifest`, `TenantId` |
| Methods & properties | `camelCase` | `$agentRunId`, `resolveTool()` |
| Local variables & parameters | `camelCase` | `$agentRunId`, `$toolId` |
| Constants (class const & `const`) | `UPPER_SNAKE_CASE` | `MAX_RETRY_ATTEMPTS` |
| Environment variables | `UPPER_SNAKE_CASE`, context-prefixed | `NIZAM_DB_URL`, `AGENTS_MAX_STEPS` |
| Namespaces (PSR-4) | `PascalCase`, rooted at `Nizam\<Context>\...` | `Nizam\Agents\Domain` |
| Files | **One class per file**, named `ClassName.php` (matches the class exactly) | `AgentRun.php`, `ResolveToolHandler.php` |
| Non-PHP asset folders (docs, infra) | `kebab-case` | `docs/`, `infra/helm/` |
| Event names (integration events) | `dot.namespaced`, past tense | `agents.run.completed` |
| Interfaces / Ports | `PascalCase`, **no `I` prefix**; named by role, not implementation | `ToolRepository`, `Clock`, `LlmProvider`, `EventPublisher` |

**Namespace / role convention** (the trailing namespace segment declares the layer; the class name declares the role):

| Namespace segment | Meaning | Example FQCN |
|-------------------|---------|--------------|
| `Domain\...` | Aggregates, entities, VOs, domain events, domain services, **ports** | `Nizam\Agents\Domain\AgentRun` |
| `Domain\Event\...` | Domain event (past tense) | `Nizam\Agents\Domain\Event\AgentRunCompleted` |
| `Domain\Port\...` | Port (interface owned by domain) | `Nizam\Agents\Domain\Port\ToolRepository` |
| `Application\...` | Use cases / command & query handlers | `Nizam\Agents\Application\StartAgentRunHandler` |
| `Application\Dto\...` | Application / boundary DTOs | `Nizam\Agents\Application\Dto\AgentRunView` |
| `Infrastructure\...` | Adapters: DB repos, brokers, HTTP clients, mappers | `Nizam\Agents\Infrastructure\Persistence\PostgresAgentRunRepository` |
| `Interface\...` | Controllers (PSR-15 handlers), consumers, CLI | `Nizam\Agents\Interface\Http\StartAgentRunController` |

Class-name suffix conventions carry the role: `*Repository` (port) / `Postgres*Repository` (adapter), `*Handler` (use case), `*Controller` / `*Consumer` (interface adapters), `*Mapper` (persistence/DTO mapping), `*Test` (PHPUnit). Value objects and events are named by the concept (`Email`, `Money`, `AgentRunCompleted`), not suffixed.

```php
// illustrative — convention only
final class AgentRun {}            // PascalCase aggregate
$agentRunId = AgentRunId::new();   // camelCase var
// file: AgentRun.php              // one class per file, name == class
```

---

## 3. Clean Architecture Layering & The Dependency Rule

Every bounded-context module has exactly four layers, each a namespace segment (§2). **Dependencies point inward only.** An outer layer may depend on an inner layer; an inner layer **MUST NOT** depend on an outer layer.

```
┌───────────────────────────────────────────────┐
│ Interface\   controllers · consumers · CLI     │  (outermost)
│   ┌───────────────────────────────────────┐    │
│   │ Infrastructure\  DB · brokers · HTTP   │    │
│   │   ┌───────────────────────────────┐    │    │
│   │   │ Application\  use cases · DTOs │    │    │
│   │   │   ┌───────────────────────┐    │    │    │
│   │   │   │ Domain\  entities·VOs │    │    │    │
│   │   │   │  events · ports       │    │    │    │
│   │   │   └───────────────────────┘    │    │    │
│   │   └───────────────────────────────┘    │    │
│   └───────────────────────────────────────┘    │
└───────────────────────────────────────────────┘
        dependencies point INWARD  ◄────
```

| Layer | MAY import | MUST NOT import | Contains |
|-------|-----------|-----------------|----------|
| `Domain\` | Only `Nizam\Core\` (shared kernel) and the PHP standard library. **Zero Composer/vendor dependencies.** | `Application\`, `Infrastructure\`, `Interface\`, any PSR *implementation* package, any DB/HTTP/broker/PSR-11 SDK, `getenv()`/`$_ENV` | Entities, aggregates, value objects, domain events, domain services, **ports** (interfaces the domain requires) |
| `Application\` | `Domain\`, `Nizam\Core\`, PSR *interface* packages (e.g. `Psr\Log`, `Psr\Clock`) as contracts only | `Infrastructure\`, `Interface\`, concrete adapters, PSR-11 container instance, any vendor implementation | Use cases (command/query handlers), application DTOs, orchestration, transaction boundaries, port *usage* |
| `Infrastructure\` | `Application\`, `Domain\`, external SDKs & Composer libraries | `Interface\` | Adapters implementing ports: DB repositories, broker publishers, HTTP clients (PSR-18), cache, secrets, mappers |
| `Interface\` | `Application\`, `Domain\` (types only), PSR-7/PSR-15 | direct DB/broker access (must go through use cases) | PSR-15 request handlers/controllers, event consumers, CLI commands, request/response DTOs |

**The port rule (Hexagonal / Ports & Adapters):** the domain and application layers *own the interfaces* (ports); infrastructure *provides the implementations* (adapters). The domain declares what it needs; it never learns how it is satisfied.

```php
// illustrative — convention only
// Nizam\Core\Domain\Port\Clock            (owned by domain)
interface Clock { public function now(): \DateTimeImmutable; }
// Nizam\Core\Infrastructure\SystemClock   (owned by infra, implements the port)
```

**Enforcement:** an architecture-fitness tool (**Deptrac** and/or **PHPArkitect**) fails CI on any inward-rule violation; layer boundaries are declared per namespace in `deptrac.yaml`. PHPStan disallowed-namespace rules back this up (e.g. `Domain\` may not reference `Psr\Container`, PDO, or any HTTP client).

**Cross-context rule:** a module **MUST NOT** import another context's `Domain\`, `Application\`, or `Infrastructure\`. Contexts communicate only via (a) integration events on the bus or (b) a published contract (a facade/port exported from the owning module's public surface). No reaching into a sibling's internals — enforced by Deptrac context layers.

---

## 4. SOLID — Applied, With Concrete Guidance

| Principle | Concrete rule in Nizam |
|-----------|------------------------|
| **S** — Single Responsibility | One use case = one class = one reason to change. A controller only translates HTTP↔use-case; it holds no business rule. A repository only persists; it holds no domain decision. |
| **O** — Open/Closed | New Tools, Integrations, and Agent skills are added as **plugins against a stable manifest + JSON Schema contract** — never by editing the registry's `match`/`switch` statements. Extend via new adapters, not by modifying the port consumer. |
| **L** — Liskov Substitution | Every adapter is fully substitutable for its port with no strengthened preconditions or weakened postconditions. The `RedisStreams` bus and the `NatsJetStream` bus MUST be interchangeable behind `EventPublisher`. Tests run against the port, not the adapter. |
| **I** — Interface Segregation | Ports are small and role-specific. Prefer `ToolReader` + `ToolWriter` over one fat `ToolRepository` when consumers differ. A use case depends only on the methods it calls. PHP interfaces stay narrow; compose with intersection types where needed. |
| **D** — Dependency Inversion | High-level policy (domain/application) depends on abstractions (ports); low-level detail (infrastructure) depends on those same abstractions. Wiring happens once, in the composition root, via the PSR-11 container (§6). |

---

## 5. DDD Tactical Conventions

| Element | Convention |
|---------|-----------|
| **Aggregate** | A single root guarding an invariant boundary, typically `final class`. All external references are **by aggregate id only** (a VO id), never by object reference across aggregates. Load, mutate, and save one aggregate per transaction. |
| **Entity** | Has identity (`private readonly EntityId $id`). Equality by id. Mutations go through intention-revealing methods that keep invariants — **no public setters**. |
| **Value Object** | **Immutable**: `final class` with `public readonly` (or `private readonly`) properties and no setters. Equality by value (an `equals()` method). Self-validating in a private constructor + static factory — an invalid VO cannot be constructed. Prefer PHP `enum` for closed sets. Examples: `TenantId`, `Email`, `Money`, `SemVer`. |
| **Domain event** | Under `Domain\Event\`, **past tense**, immutable (`readonly`), carries only ids + primitives (no aggregate references). Recorded by the aggregate, published via the transactional outbox (§ EDA). |
| **Domain service** | Stateless; holds a rule that doesn't belong to a single aggregate. Lives under `Domain\Service\`. |
| **Factory** | Static `create()` on the aggregate for construction that enforces invariants; `fromEvents()`/`reconstitute()` for repository/event-store reconstitution. |
| **Repository** | A **port** in `Domain\Port\`, an adapter in `Infrastructure\`. Interface speaks the domain language (`findById`, `save`), returns domain objects, and leaks no SQL/PDO. |
| **Ubiquitous language** | Class, method, and namespace names match the canon's bounded-context vocabulary exactly (Agent, Tool, Automation, Intent…). No synonyms. |

```php
// illustrative — convention only  (VO is immutable + self-validating)
final class TenantId
{
    private function __construct(public readonly string $value) {}

    /** @throws ValidationError when $value is not a UUID v7 */
    public static function of(string $value): self { /* validate uuid v7 */ }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
```

Critical aggregates — **Agent runs, Tool executions, Automations** — are **event-sourced** (canon §3): their state is derived from an ordered event stream, and they emit events as the source of truth.

---

## 6. Dependency Injection (PSR-11)

- Wiring lives **only** in the composition root (a per-module service-definition file consumed by the **PSR-11 container**), never in domain/application classes.
- Depend on **ports (interface types)**, not on concrete classes. Bind interface → adapter in the container definitions.
- **Constructor injection only.** No property injection, no service locator (the container is **never** injected into domain/application code), no static singletons for stateful services.
- The container is built once at boot; services are wired by their interface FQCN as the identifier.

```php
// illustrative — convention only (container definitions)
return [
    Clock::class => fn() => new SystemClock(),
    ToolRepository::class => fn(ContainerInterface $c) =>
        new PostgresToolRepository($c->get(\PDO::class)),
];
// consumer:
final class ResolveToolHandler
{
    public function __construct(private readonly ToolRepository $tools) {}
}
```

**Rule:** application/domain code references the **port type** only. It never `new`s an adapter, never calls `$container->get()`, and never imports an infrastructure class.

---

## 7. Error Handling — Typed Errors + Result Pattern

Nizam distinguishes **expected outcomes** (business failures) from **exceptions** (bugs / unrecoverable faults).

- **Expected failures MUST use a `Result` type**, not thrown exceptions. A use case returns `Result` (an `Ok`/`Err` object, or a sealed `Success|Failure` union expressed via PHPDoc generics `@return Result<Output, DomainError>`). Callers pattern-match on `isOk()`; they do not `try/catch` for control flow.
- **Domain errors are typed** — a sealed hierarchy (abstract base + `final` subclasses, or a `DomainError` interface) with a stable `code()`, a machine-readable payload, and a human, translatable message key. No `throw new \Exception("string")` in domain/application.
- **`throw` is reserved** for programmer errors (`\LogicException`) and truly exceptional infra faults (`\RuntimeException`, DB down). These are caught once, at the interface boundary, by a PSR-15 error-handling middleware that maps them to a safe response + a logged, traced incident.
- **No error crosses a layer boundary silently.** An adapter that catches an SDK/`PDOException` MUST translate it to a typed `Result` error or a known infra exception — never swallow, never rethrow a raw vendor exception outward.

```php
// illustrative — convention only
/** @template T @template E */
interface Result { public function isOk(): bool; }

interface DomainError { public function code(): string; }
final class ToolNotFound implements DomainError {
    public function __construct(public readonly string $toolId) {}
    public function code(): string { return 'TOOLS.TOOL_NOT_FOUND'; }
}
```

Error `code()` values are namespaced (`AGENTS.RUN_LIMIT_EXCEEDED`), documented in `docs/03-Architecture.md`, and mapped to HTTP/RFC 7807 problem details at the interface layer.

---

## 8. Configuration (12-Factor)

- **All config comes from the environment.** No config baked into images; no per-tenant code branches by hostname.
- **A validated, typed config object is built once at boot.** If validation fails, the process **MUST exit non-zero before serving traffic** (fail fast, no partial boot).
- Environment access is centralized: `getenv()` / `$_ENV` / `$_SERVER` are read **exclusively** inside the config module — never in domain/application/adapters. The parsed, typed config object is the *only* way code reads config. A validator (e.g. `symfony/validator` component or a hand-rolled typed loader) checks presence, type, and format at boot.
- Secrets are never in env files committed to the repo; they resolve through the secrets port (Vault/KMS). `.env.example` documents every key with a comment, no real values.
- Config is **environment-parameterized, not environment-conditional**: no `if ($env === 'prod')` business branches.

```php
// illustrative — convention only
final class Config
{
    private function __construct(public readonly string $dbUrl, public readonly string $appEnv) {}

    /** @throws ConfigError when a required var is missing/invalid — process exits at boot */
    public static function fromEnv(): self { /* validate NIZAM_DB_URL, APP_ENV ∈ {dev,test,prod} */ }
}
```

---

## 9. Logging Conventions

- **Structured JSON only** via **Monolog** through the **PSR-3** `LoggerInterface`. No `echo`, `var_dump`, `error_log`, or `print` in committed code (PHP_CodeSniffer / PHPStan forbidden-function rule; test setup excepted).
- Every log line carries: `timestamp`, `level`, `service`, `context` (bounded context), `tenant_id`, `correlation_id` (a.k.a. `trace_id`), `span_id`, `event` (a short stable code), and `message`. A Monolog `JsonFormatter` + processors add these automatically.
- **Correlation id** is generated at ingress (or propagated via W3C `traceparent`), stored on the per-request **`RequestContext`/`TenantContext`** service (PHP is share-nothing per request — no `AsyncLocalStorage` needed), and attached to every log, event, and outbound call via a Monolog processor.
- **No PII, no secrets, no raw payloads** in logs. Redact by allow-list, not by best effort. Tokens, API keys, emails, phone numbers, and credential fields are redacted in a Monolog processor before the formatter.
- Log **levels** carry meaning: `error` = needs attention/alert; `warning` = degraded but handled; `info` = business milestone (`agent.run.completed`); `debug` = developer detail (off in prod).
- Logs are for machines first: put variable data in the context array, keep the `message` templateable and low-cardinality.

---

## 10. Ports & Adapters Convention (summary)

| Concept | Where it lives | Naming | Rule |
|---------|----------------|--------|------|
| Port (interface) | `Domain\Port\` or `Application\Port\` | `PascalCase`, role-named interface | Owned by the inside; small; no vendor types leak in |
| Adapter (impl) | `Infrastructure\...` | `<Vendor><Role>` (e.g. `PostgresToolRepository`, `NatsEventPublisher`) | Owned by the outside; one adapter per external system |
| Binding | PSR-11 container definitions (composition root) | interface FQCN → adapter | The only place the two meet |

Every external dependency the canon names — **Bayan (via Bayan Gateway ACL), n8n, the LLM (`LlmProvider`), the event broker, the queue, secrets, the DB** — sits behind a port and is replaceable without touching domain/application code. Composer libraries (Monolog, the messenger component, the HTTP client, the container) are always consumed **behind a port**, never leaked into domain/application.

---

## 11. Testing Strategy & Pyramid

```
        ▲  fewer, slower, higher-confidence
        │        ┌───────────┐
        │        │    e2e    │   full stack, real infra (docker-compose/k8s)
        │      ┌─┴───────────┴─┐
        │      │   contract    │  provider/consumer (OpenAPI/AsyncAPI, Pact)
        │    ┌─┴───────────────┴─┐
        │    │   integration     │  adapters vs real Postgres/Redis/NATS
        │  ┌─┴───────────────────┴─┐
        │  │     application       │  use cases with in-memory/fake adapters
        │┌─┴───────────────────────┴─┐
        ││       unit / domain        │  entities, VOs, domain services — pure
        │└────────────────────────────┘
        │  many, fast, deterministic
```

| Level | Scope | Dependencies | Target share |
|-------|-------|--------------|--------------|
| Unit / domain | One class/function; entities, VOs, domain services, use-case logic | None (pure PHP) | ~70% |
| Application | A use case end-to-end with **fake/in-memory ports** | Fakes only | included above |
| Integration | An adapter against the real technology | Real Postgres/Redis/NATS via Docker (Testcontainers for PHP) | ~20% |
| Contract | Producers/consumers honor OpenAPI/AsyncAPI schemas | Pact / schema validators | ~5% |
| e2e | A user/intent flow across the running system | Full stack | ~5% |

**Coverage targets (CI-gated):** domain layer **≥ 90%** line & branch; application **≥ 85%**; overall repo **≥ 80%**. Coverage never drops below the current baseline (ratchet). Measured with PHPUnit + Xdebug/PCOV (`--coverage-clover`).

**Test conventions:**
- Framework: **PHPUnit** (Pest optional as a thin layer). HTTP e2e drives the app through PSR-7/PSR-15 or a running container.
- Files named `*Test.php` under `tests/`, mirroring the `src/` namespace under a `Tests\` PSR-4 root. Unit vs integration vs e2e separated by PHPUnit `<testsuite>`s.
- Naming: `final class AgentRunTest` → `public function test_it_completes_when_all_steps_succeed(): void` (or `@test`). Describe the behavior, not the method.
- Arrange–Act–Assert, one logical assertion theme per test. No shared mutable state (`@backupGlobals` disabled by design); tests are order-independent. No `sleep()` — use a fake `Clock` and awaited/collected events.
- Tests depend on **ports**, so the same suite validates every adapter (Liskov, §4) via a shared abstract test case.

---

## 12. Commit & Branch Conventions

- **Conventional Commits** are mandatory (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`, `build:`, `ci:`, `perf:`). Scope = bounded context: `feat(agents): …`. Breaking change → `!` and a `BREAKING CHANGE:` footer.
- **Trunk-based development.** `main` is always releasable and protected. Work happens on **short-lived branches** (`<type>/<context>-<short-desc>`, e.g. `feat/agents-run-guardrails`), merged via PR within days, not weeks. No long-running release branches (no GitFlow).
- Squash-merge to `main`; the squashed subject is a Conventional Commit and feeds automated SemVer + changelog.
- Every PR links its task/issue and updates docs in the same PR (§17).
- Commit trailers are appended per repository policy.

---

## 13. Code Review Checklist (a PR MUST pass all)

- [ ] **Layering:** no inward-rule violation (Deptrac/PHPArkitect green); no cross-context internal import.
- [ ] **Ports:** new external dependency is behind a port + adapter, bound in the PSR-11 container; no vendor type leaks inward.
- [ ] **Types:** `declare(strict_types=1)` present; full param/return/property types; no unexplained `mixed`; no unexplained `@phpstan-ignore`/`@psalm-suppress`; DTOs typed at boundaries.
- [ ] **Errors:** expected failures return `Result` with typed errors; nothing swallowed; no raw vendor exception rethrown outward.
- [ ] **Domain integrity:** invariants enforced in aggregates; VOs immutable (`readonly`, no setters); no cross-aggregate object refs.
- [ ] **Multi-tenancy:** every query/command is tenant-scoped via `TenantContext`; no path bypasses RLS.
- [ ] **Security:** input validated at the edge; authz checked; no secrets/PII in code or logs (§16).
- [ ] **Events:** state changes emit domain events via the outbox; event names past-tense & versioned.
- [ ] **Tests:** new behavior covered at the right pyramid level; coverage ratchet holds.
- [ ] **Config:** new config validated at boot; `.env.example` updated.
- [ ] **Docs:** **PHPDoc on every new/changed class** (constitution rule 15); module `README.md` and affected `docs/` updated in this PR.
- [ ] **Observability:** meaningful logs/metrics/traces added for new paths; correlation id preserved.
- [ ] **Naming:** matches §2 exactly (PSR: PascalCase classes, camelCase members, one class per file).
- [ ] **DoD:** the Definition of Done (§18) is fully met.

---

## 14. Linting, Formatting & Static Analysis

| Tool | Role | Rule |
|------|------|------|
| **PHPStan** (`level: max`, `phpstan/phpstan-strict-rules`, `phpstan/extension-installer`, `bleedingEdge`) | Static analysis / correctness | Zero errors to merge; baseline is append-only and shrinking |
| **Psalm** (`errorLevel="1"`, `findUnusedCode`, `findUnusedBaselineEntry`) | Complementary static analysis / taint | Zero new issues; taint analysis on request-handling paths |
| **Deptrac** and/or **PHPArkitect** | Architecture / dependency boundaries | Fails CI on any layer or cross-context violation |
| **PHP-CS-Fixer** and **PHP_CodeSniffer** | Formatting & style (PER / PSR-12) | Single source of truth for style; `--dry-run`/`phpcs` gate CI, `phpcbf`/`fix` locally |
| **`php -l` / composer validate** | Syntax & manifest sanity | Must pass |
| **captainhook** (or a Composer-managed git hook) | Pre-commit / commit-msg | Runs PHP-CS-Fixer + PHPStan on staged files; enforces Conventional Commits |

Formatting standard is **PER-CS / PSR-12**: 4-space indent, `<?php` + `declare(strict_types=1)` header, one class per file, LF line endings, trailing comma in multiline arrays/args, short array syntax `[]`, ordered imports, no unused imports. Config is committed (`.php-cs-fixer.dist.php`, `phpcs.xml.dist`, `phpstan.neon.dist`, `psalm.xml`, `deptrac.yaml`) and **not** overridable per developer.

---

## 15. API / DTO Conventions

- **REST is URL-versioned** (`/v1/...`); breaking changes bump the version. Contracts are **OpenAPI 3.1** (source of truth in `docs/openapi/`), events are **AsyncAPI 2.6** (`docs/asyncapi/`). HTTP is handled with **PSR-7 messages** and **PSR-15 middleware/handlers**.
- **DTOs are a boundary type**, distinct from domain models — plain immutable PHP objects (`readonly` properties). Never expose an aggregate directly; map domain → response DTO in the interface layer via a `*Mapper`.
- Request DTOs are validated at the edge (JSON Schema / a validator component); an invalid request never reaches a use case.
- JSON field casing is **`camelCase`** on the wire; DB `snake_case` never leaks to clients. Serialize/deserialize with explicit mappers, not by reflecting domain objects.
- Errors follow **RFC 7807 problem+json** with a stable `type`/`code`, tenant-safe `detail`, and no internal leakage (no stack traces, no SQL).
- Pagination is cursor-based; timestamps are ISO-8601 UTC; ids are UUID v7 strings.
- Idempotent write endpoints accept an `Idempotency-Key` header.

---

## 16. Security Coding Rules

- **Validate all input at the boundary**; treat every external value (HTTP, event, tool arg, LLM output) as untrusted until schema-validated.
- **Tenant scope is mandatory** on every data access; rely on RLS as the enforcing boundary but also filter in the query (defense in depth) using `TenantContext`. No cross-tenant reads, ever.
- **Least privilege:** authz (RBAC + ABAC) checked in the application layer before any effect; tools carry permission scopes and are permission-checked at invocation.
- **No secrets in code, logs, errors, tests, or fixtures.** Secrets resolve via the secrets port at runtime. Fail the build on detected secrets (secret-scanning in CI).
- **No dynamic code execution** (`eval`, `assert` with strings, `create_function`, variable-variables for dispatch, unvetted `exec`/`shell_exec`/`proc_open`, unsafe `unserialize` of external data — use JSON). Plugin/tool execution is sandboxed against its declared contract.
- **Output encoding & injection safety:** **prepared statements / parameterized queries only** (PDO with bound params; no string-built SQL); encode on output (`htmlspecialchars` where HTML is produced); sanitize anything forwarded to n8n / external systems.
- **Dependencies:** pinned via `composer.lock`; CI runs SCA (`composer audit` / Roave Security Advisories) and blocks known-critical CVEs; new deps require review.
- **AuthN:** OAuth2/OIDC, short-lived JWT access + rotating refresh; mTLS service-to-service. Never hand-roll crypto — use `sodium`/`password_hash`.
- **PII handling** follows the data-classification in `docs/10-Security-Strategy.md`; log redaction is mandatory (§9).

---

## 17. Documentation Requirements

Per the **[Project Constitution](../README.md)** (binding, non-negotiable):

- **PHPDoc on every class (constitution rule 15):** every class, interface, trait, and enum carries a PHPDoc block stating its purpose. Public methods carry PHPDoc **where types alone are insufficient** — generic collections (`@param list<ToolId>`, `@return Result<AgentRunView, DomainError>`), and **every `@throws`** a caller must handle.
- **Every module (package and bounded context) has a `README.md`** describing its purpose, public contract (ports/events it exposes), and its layer map.
- **Docs are updated in the same task/PR that changes behavior.** A PR that changes an API, event, schema, or config **without** a corresponding `docs/` and README update fails review (§13).
- Non-obvious decisions are recorded as an ADR under `docs/adr/`.
- OpenAPI/AsyncAPI specs are the source of truth for contracts and are regenerated/verified in CI.

```php
// illustrative — convention only (PHPDoc on every class + @throws)
/**
 * Starts a new agent run for the current tenant and records the resulting events.
 */
final class StartAgentRunHandler
{
    /**
     * @return Result<AgentRunView, DomainError>
     * @throws \RuntimeException on unrecoverable infrastructure failure
     */
    public function handle(StartAgentRunCommand $command): Result { /* ... */ }
}
```

---

## 18. Definition of Done

A change is **Done** only when **all** hold:

1. Meets these coding standards; passes PHP-CS-Fixer/PHP_CodeSniffer (PER/PSR-12), **PHPStan (max)**, **Psalm**, and Deptrac/PHPArkitect boundary checks with zero errors; `declare(strict_types=1)` everywhere.
2. Behavior covered by PHPUnit tests at the correct pyramid level; coverage ratchet satisfied (domain ≥ 90%); CI green.
3. Multi-tenant, secure, and observable: tenant-scoped via `TenantContext`, authz-checked, logs/metrics/traces present, no PII/secret leakage.
4. Errors typed and surfaced via `Result`/problem+json; failure paths handled (retry/idempotency/compensation where relevant).
5. Contracts updated (OpenAPI/AsyncAPI) and consistent; events versioned via the outbox.
6. **PHPDoc on every new/changed class**, module `README.md`, and affected `docs/` updated in the same PR (constitution).
7. Conventional-commit PR, linked to its task, reviewed and approved against §13, squash-merged to a releasable `main`.

---

## Related Documents

- [00-Vision.md](./00-Vision.md) — Why Nizam exists
- [README.md — Project Constitution](../README.md) — Binding engineering constitution (documentation rule)
- [14-Folder-Structure.md](./14-Folder-Structure.md) — Where these conventions live on disk
- [10-Security-Strategy.md](./10-Security-Strategy.md) — Security & data-classification detail
- [03-Architecture.md](./03-Architecture.md) — Namespaced error codes
- [21-Database-Design.md](./21-Database-Design.md) — DB naming, tenancy, audit conventions
- [../README.md](../README.md) — Project entry point

---

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial enforceable coding standards for Phase 1. |
