# Coding Standards

> The enforceable engineering standard every Nizam module, package, and contribution must follow — the rulebook later phases will lint, review, and gate against.

**Status: Approved (Phase 1) | Version: 1.0.0 | Last updated: 2026-07-01 | Owner: Architecture (Nizam Core)**

---

## 0. How to Read This Document

This document defines standards. Standards are **normative**: the words **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, and **MAY** are used in the RFC 2119 sense. A "MUST" is a merge blocker; a "SHOULD" is a review default that requires a written justification to violate.

This is a documentation-only phase. Every code snippet below is tiny and marked **`illustrative — convention only`**. No snippet is product logic; each exists solely to fix a naming or shape convention so later phases have one unambiguous target.

The constitution requirement is absolute: **every module ships a `README.md`, and documentation is updated in the same task that changes behavior.** See §17 and the [Project Constitution](../README.md).

---

## 1. Language & Runtime

| Concern | Standard | Enforcement |
|---------|----------|-------------|
| Runtime | Node.js **22 LTS** only. No feature relying on a newer runtime. | `.nvmrc`, `engines` in `package.json`, CI matrix |
| Language | TypeScript **5.x**, `strict: true` (implies `strictNullChecks`, `noImplicitAny`, `strictFunctionTypes`, etc.) | `tsconfig.base.json`, CI `tsc --noEmit` |
| Module system | ESM (`"type": "module"`). No CommonJS `require` in source. | ESLint `import/no-commonjs` |
| Target | `ES2023`, `moduleResolution: NodeNext` | `tsconfig.base.json` |
| `any` | **MUST NOT** appear in committed code. Use `unknown` + narrowing, generics, or a typed model. `// @ts-expect-error` requires an inline reason and a linked issue. | ESLint `@typescript-eslint/no-explicit-any: error` |
| Non-null `!` | **MUST NOT** be used to silence the compiler. Prove non-null via control flow or a `Result`/guard. | ESLint `@typescript-eslint/no-non-null-assertion: error` |
| `enum` | Prefer `as const` union types or object literals over TS `enum` (erasable, tree-shakeable). | Review default |

**Compiler flags that MUST be on:** `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`, `noFallthroughCasesInSwitch`, `noImplicitReturns`, `forceConsistentCasingInFileNames`, `verbatimModuleSyntax`.

```jsonc
// illustrative — convention only (tsconfig.base.json excerpt)
{ "compilerOptions": { "strict": true, "noUncheckedIndexedAccess": true, "exactOptionalPropertyTypes": true } }
```

---

## 2. Naming Conventions

Naming is **FIXED** by the canon. It is not a matter of taste.

| Scope | Case | Example |
|-------|------|---------|
| Database identifiers (tables, columns, indexes, constraints) | `snake_case` | `agent_run`, `tenant_id`, `created_at` |
| TypeScript variables, functions, params, properties | `camelCase` | `agentRunId`, `resolveTool()` |
| Classes, aggregates, entities, VOs, types, interfaces, enums | `PascalCase` | `AgentRun`, `ToolManifest`, `TenantId` |
| Files & folders | `kebab-case` | `agent-run.aggregate.ts`, `resolve-tool/` |
| Constants (true compile-time constants) | `SCREAMING_SNAKE_CASE` | `MAX_RETRY_ATTEMPTS` |
| Environment variables | `SCREAMING_SNAKE_CASE`, context-prefixed | `NIZAM_DB_URL`, `AGENTS_MAX_STEPS` |
| Event names (integration events) | `dot.namespaced`, past tense | `agents.run.completed` |
| Interfaces | `PascalCase`, **no `I` prefix** | `ToolRepository` (not `IToolRepository`) |
| Ports (domain interfaces) | Named by role, not implementation | `ClockPort`, `LlmProvider`, `EventPublisher` |

**File suffix convention** (the second dot-segment declares the file's role):

| Suffix | Meaning |
|--------|---------|
| `*.aggregate.ts` | Aggregate root |
| `*.entity.ts` | Entity (non-root) |
| `*.vo.ts` | Value object |
| `*.event.ts` | Domain event |
| `*.port.ts` | Port (interface owned by domain/application) |
| `*.use-case.ts` | Application use case / handler |
| `*.dto.ts` | Data transfer object (interface boundary) |
| `*.repository.ts` | Repository port; adapter is `*.repository.pg.ts` |
| `*.controller.ts` / `*.consumer.ts` | Interface adapters |
| `*.mapper.ts` | Persistence/DTO mapping |
| `*.spec.ts` / `*.e2e-spec.ts` | Tests |

```ts
// illustrative — convention only
class AgentRun {}                 // PascalCase aggregate
const agentRunId = newId();       // camelCase var
// file: agent-run.aggregate.ts   // kebab-case file
```

---

## 3. Clean Architecture Layering & The Dependency Rule

Every bounded-context module has exactly four layers. **Dependencies point inward only.** An outer layer may import an inner layer; an inner layer **MUST NOT** import an outer layer.

```
┌───────────────────────────────────────────────┐
│ interface/   controllers · consumers · CLI     │  (outermost)
│   ┌───────────────────────────────────────┐    │
│   │ infrastructure/  DB · brokers · HTTP   │    │
│   │   ┌───────────────────────────────┐    │    │
│   │   │ application/  use cases · DTOs │    │    │
│   │   │   ┌───────────────────────┐    │    │    │
│   │   │   │ domain/  entities·VOs │    │    │    │
│   │   │   │  events · ports       │    │    │    │
│   │   │   └───────────────────────┘    │    │    │
│   │   └───────────────────────────────┘    │    │
│   └───────────────────────────────────────┘    │
└───────────────────────────────────────────────┘
        dependencies point INWARD  ◄────
```

| Layer | MAY import | MUST NOT import | Contains |
|-------|-----------|-----------------|----------|
| `domain/` | Only `@nizam/core` (shared kernel) and pure language/stdlib. | `application/`, `infrastructure/`, `interface/`, NestJS, any DB/HTTP/broker SDK, `process.env` | Entities, aggregates, value objects, domain events, domain services, **ports** (interfaces the domain requires) |
| `application/` | `domain/`, `@nizam/core` | `infrastructure/`, `interface/`, concrete adapters, NestJS decorators on domain types | Use cases (command/query handlers), application DTOs, orchestration, transaction boundaries, port *usage* |
| `infrastructure/` | `application/`, `domain/`, external SDKs | `interface/` | Adapters implementing ports: DB repositories, broker publishers, HTTP clients, cache, secrets, mappers |
| `interface/` | `application/`, `domain/` (types only) | direct DB/broker access (must go through use cases) | NestJS controllers, GraphQL resolvers, event consumers, CLI commands, request/response DTOs |

**The port rule (Hexagonal / Ports & Adapters):** the domain and application layers *own the interfaces* (ports); infrastructure *provides the implementations* (adapters). The domain declares what it needs; it never learns how it is satisfied.

```ts
// illustrative — convention only
// domain/ports/clock.port.ts        (owned by domain)
export interface ClockPort { now(): Date }
// infrastructure/system-clock.adapter.ts  (owned by infra, implements the port)
```

**Enforcement:** a dependency-boundary linter (`eslint-plugin-boundaries` or `dependency-cruiser`) fails CI on any inward-rule violation. Layer boundaries are declared per folder in `.dependency-cruiser.cjs`.

**Cross-context rule:** a module **MUST NOT** import another context's `domain/`, `application/`, or `infrastructure/`. Contexts communicate only via (a) integration events on the bus or (b) a published contract (a facade/port exported from the owning module's public index). No reaching into a sibling's internals.

---

## 4. SOLID — Applied, With Concrete Guidance

| Principle | Concrete rule in Nizam |
|-----------|------------------------|
| **S** — Single Responsibility | One use case = one class = one reason to change. A controller only translates HTTP↔use-case; it holds no business rule. A repository only persists; it holds no domain decision. |
| **O** — Open/Closed | New Tools, Integrations, and Agent skills are added as **plugins against a stable manifest + JSON Schema contract** — never by editing the registry's switch statements. Extend via new adapters, not by modifying the port consumer. |
| **L** — Liskov Substitution | Every adapter is fully substitutable for its port with no strengthened preconditions or weakened postconditions. The `RedisStreams` bus and the `NatsJetStream` bus MUST be interchangeable behind `EventBus`. Tests run against the port, not the adapter. |
| **I** — Interface Segregation | Ports are small and role-specific. Prefer `ToolReader` + `ToolWriter` over one fat `ToolRepository` when consumers differ. A use case depends only on the methods it calls. |
| **D** — Dependency Inversion | High-level policy (domain/application) depends on abstractions (ports); low-level detail (infrastructure) depends on those same abstractions. Wiring happens once, in the composition root (NestJS module), via tokens (§6). |

---

## 5. DDD Tactical Conventions

| Element | Convention |
|---------|-----------|
| **Aggregate** | Named `*.aggregate.ts`, a single root guarding an invariant boundary. All external references are **by aggregate id only**, never by object reference across aggregates. Load, mutate, and save one aggregate per transaction. |
| **Entity** | Has identity (`id: EntityIdVO`). Equality by id. Mutations go through methods that keep invariants, never public setters. |
| **Value Object** | **Immutable** (`readonly` fields, no setters). Equality by value. Self-validating in the constructor/factory — an invalid VO cannot be constructed. Examples: `TenantId`, `Email`, `Money`, `SemVer`. |
| **Domain event** | `*.event.ts`, **past tense**, immutable, carries only ids + primitives (no aggregate references). Emitted by the aggregate, published via the transactional outbox (§ EDA). |
| **Domain service** | Stateless; holds a rule that doesn't belong to a single aggregate. Lives in `domain/services/`. |
| **Factory** | Static `create()` on the aggregate for construction that enforces invariants; `rehydrate()`/`fromState()` for repository reconstitution. |
| **Repository** | A **port** in `domain/`, an adapter in `infrastructure/`. Interface speaks the domain language (`findById`, `save`), not SQL. |
| **Ubiquitous language** | Names in code match the canon's bounded-context vocabulary exactly (Agent, Tool, Automation, Intent…). No synonyms. |

```ts
// illustrative — convention only  (VO is immutable + self-validating)
export class TenantId {
  private constructor(public readonly value: string) {}
  static of(v: string): Result<TenantId, ValidationError> { /* validate uuid v7 */ }
}
```

Critical aggregates — **Agent runs, Tool executions, Automations** — are **event-sourced** (canon §3): their state is derived from an ordered event stream, and they emit events as the source of truth.

---

## 6. Dependency Injection (NestJS)

- Wiring lives **only** in the module's composition root (`*.module.ts`), never in domain/application classes.
- Depend on **ports via injection tokens**, not on concrete classes. Bind token → adapter in the module `providers`.
- Constructor injection only. No property injection, no service locator, no static singletons for stateful services.
- Tokens are `Symbol`-based and colocated with the port.

```ts
// illustrative — convention only
export const CLOCK = Symbol('ClockPort');
@Module({ providers: [{ provide: CLOCK, useClass: SystemClock }] })
export class CoreModule {}
// consumer: constructor(@Inject(CLOCK) private readonly clock: ClockPort) {}
```

**Rule:** application/domain code references the **token + port type** only. It never `new`s an adapter and never imports an infrastructure class.

---

## 7. Error Handling — Typed Errors + Result Pattern

Nizam distinguishes **expected outcomes** (business failures) from **exceptions** (bugs / unrecoverable faults).

- **Expected failures MUST use the `Result<T, E>` type**, not thrown exceptions. A use case returns `Result<Output, DomainError>`. Callers pattern-match; they do not `try/catch` for control flow.
- **Domain errors are typed** — a discriminated union / sealed hierarchy with a stable `code`, a machine field, and a human, translatable message key. No `throw new Error("string")` in domain/application.
- **`throw` is reserved** for programmer errors and truly exceptional infra faults (DB down). These are caught once, at the interface boundary, by a global exception filter that maps them to a safe response + a logged, traced incident.
- **No error crosses a layer boundary silently.** An adapter that catches an SDK exception MUST translate it to a typed `Result` error or a known infra exception — never swallow, never rethrow a raw vendor error outward.

```ts
// illustrative — convention only
type Result<T, E> = { ok: true; value: T } | { ok: false; error: E };
type ToolError =
  | { code: 'TOOL_NOT_FOUND'; toolId: string }
  | { code: 'PERMISSION_DENIED'; scope: string };
```

Error `code` values are namespaced (`AGENTS.RUN_LIMIT_EXCEEDED`), documented in `docs/03-Architecture.md`, and mapped to HTTP/RFC 7807 problem details at the interface layer.

---

## 8. Configuration (12-Factor)

- **All config comes from the environment.** No config baked into images; no per-tenant code branches by hostname.
- **A validated, typed config object is built once at boot.** If validation fails, the process **MUST exit non-zero before serving traffic** (fail fast, no partial boot).
- Validation uses a schema (`zod`); the parsed result is the *only* way code reads config. `process.env` is read **exclusively** inside the config module — never in domain/application/adapters.
- Secrets are never in env files committed to the repo; they resolve through the secrets port (Vault/KMS). `.env.example` documents every key with a comment, no real values.
- Config is **environment-parameterized, not environment-conditional**: no `if (env === 'prod')` business branches.

```ts
// illustrative — convention only
const Env = z.object({ NIZAM_DB_URL: z.string().url(), NODE_ENV: z.enum(['dev','test','prod']) });
export const config = Env.parse(process.env); // throws → process exits at boot
```

---

## 9. Logging Conventions

- **Structured JSON only** via `pino`. No `console.log` in committed code (ESLint `no-console: error`, test setup excepted).
- Every log line carries: `timestamp`, `level`, `service`, `context` (bounded context), `tenant_id`, `correlation_id` (a.k.a. `trace_id`), `span_id`, `event` (a short stable code), and `message`.
- **Correlation id** is generated at ingress (or propagated via W3C `traceparent`), stored in async-local context, and attached to every log, event, and outbound call automatically.
- **No PII, no secrets, no raw payloads** in logs. Redact by allow-list, not by best effort. Tokens, API keys, emails, phone numbers, and credential fields are redacted at the serializer.
- Log **levels** carry meaning: `error` = needs attention/alert; `warn` = degraded but handled; `info` = business milestone (`agent.run.completed`); `debug` = developer detail (off in prod).
- Logs are for machines first: put variable data in fields, keep the `message` templateable and low-cardinality.

---

## 10. Ports & Adapters Convention (summary)

| Concept | Where it lives | Naming | Rule |
|---------|----------------|--------|------|
| Port (interface) | `domain/ports/` or `application/ports/` | `*.port.ts`, role-named | Owned by the inside; small; no vendor types leak in |
| Adapter (impl) | `infrastructure/adapters/` | `<vendor>-<role>.adapter.ts` | Owned by the outside; one adapter per external system |
| Binding | `*.module.ts` composition root | token → adapter | The only place the two meet |

Every external dependency the canon names — **Bayan (via Bayan Gateway ACL), n8n, the LLM (`LlmProvider`), the event broker, secrets, the DB** — sits behind a port and is replaceable without touching domain/application code.

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
| Unit / domain | One class/function; entities, VOs, domain services, use-case logic | None (pure) | ~70% |
| Application | A use case end-to-end with **fake/in-memory ports** | Fakes only | included above |
| Integration | An adapter against the real technology | Real Postgres/Redis/NATS via Testcontainers | ~20% |
| Contract | Producers/consumers honor OpenAPI/AsyncAPI schemas | Pact / schema validators | ~5% |
| e2e | A user/intent flow across the running system | Full stack | ~5% |

**Coverage targets (CI-gated):** domain layer **≥ 90%** line & branch; application **≥ 85%**; overall repo **≥ 80%**. Coverage never drops below the current baseline (ratchet).

**Test conventions:**
- Framework: Vitest (unit/app/integration), Supertest + Nest testing module (e2e).
- File `*.spec.ts` colocated with source; `*.e2e-spec.ts` under `test/`.
- Naming: `describe('AgentRun')` → `it('completes when all steps succeed')`. Describe the behavior, not the method.
- Arrange–Act–Assert, one logical assertion theme per test. No shared mutable state between tests. No sleeps — use fake clocks/awaited events.
- Tests depend on **ports**, so the same suite validates every adapter (Liskov, §4).

---

## 12. Commit & Branch Conventions

- **Conventional Commits** are mandatory (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`, `build:`, `ci:`, `perf:`). Scope = bounded context: `feat(agents): …`. Breaking change → `!` and a `BREAKING CHANGE:` footer.
- **Trunk-based development.** `main` is always releasable and protected. Work happens on **short-lived branches** (`<type>/<context>-<short-desc>`, e.g. `feat/agents-run-guardrails`), merged via PR within days, not weeks. No long-running release branches (no GitFlow).
- Squash-merge to `main`; the squashed subject is a Conventional Commit and feeds automated SemVer + changelog.
- Every PR links its task/issue and updates docs in the same PR (§17).
- Commit trailers are appended per repository policy.

---

## 13. Code Review Checklist (a PR MUST pass all)

- [ ] **Layering:** no inward-rule violation; no cross-context internal import.
- [ ] **Ports:** new external dependency is behind a port + adapter, bound by token.
- [ ] **Types:** no `any`, no `!`, no unexplained `@ts-expect-error`; DTOs typed at boundaries.
- [ ] **Errors:** expected failures return `Result` with typed errors; nothing swallowed.
- [ ] **Domain integrity:** invariants enforced in aggregates; VOs immutable; no cross-aggregate object refs.
- [ ] **Multi-tenancy:** every query/command is tenant-scoped; no path bypasses RLS.
- [ ] **Security:** input validated at the edge; authz checked; no secrets/PII in code or logs (§16).
- [ ] **Events:** state changes emit domain events via the outbox; event names past-tense & versioned.
- [ ] **Tests:** new behavior covered at the right pyramid level; coverage ratchet holds.
- [ ] **Config:** new config validated in the schema; `.env.example` updated.
- [ ] **Docs:** module `README.md` and affected `docs/` updated in this PR (constitution).
- [ ] **Observability:** meaningful logs/metrics/traces added for new paths; correlation id preserved.
- [ ] **Naming:** matches §2 exactly.
- [ ] **DoD:** the Definition of Done (§18) is fully met.

---

## 14. Linting & Formatting

| Tool | Role | Rule |
|------|------|------|
| **ESLint** (`@typescript-eslint`, `eslint-plugin-boundaries`/`dependency-cruiser`, `eslint-plugin-import`, `eslint-plugin-sonarjs`) | Correctness & architecture | Zero errors to merge; warnings block on CI in "strict" projects |
| **Prettier** | Formatting only | Single source of truth for style; ESLint does not fight it (`eslint-config-prettier`) |
| **`tsc --noEmit`** | Type check | Must pass |
| **commitlint** | Commit message shape | Enforces Conventional Commits |
| **lint-staged + husky** | Pre-commit | Formats & lints staged files locally |

Formatting standard: 2-space indent, single quotes, trailing commas, semicolons on, print width 100, LF line endings. Config is committed (`.prettierrc`, `eslint.config.mjs`) and **not** overridable per developer.

---

## 15. API / DTO Conventions

- **REST is URL-versioned** (`/v1/...`); breaking changes bump the version. Contracts are **OpenAPI 3.1** (source of truth in `docs/openapi/`), events are **AsyncAPI 2.6** (`docs/asyncapi/`).
- **DTOs are a boundary type**, distinct from domain models. Never expose an aggregate directly; map domain → response DTO in the interface layer via a `*.mapper.ts`.
- Request DTOs are validated at the edge (schema/`class-validator`); an invalid request never reaches a use case.
- JSON field casing is **`camelCase`** on the wire; DB `snake_case` never leaks to clients.
- Errors follow **RFC 7807 problem+json** with a stable `type`/`code`, tenant-safe `detail`, and no internal leakage.
- Pagination is cursor-based; timestamps are ISO-8601 UTC; ids are UUID v7 strings.
- Idempotent write endpoints accept an `Idempotency-Key` header.

---

## 16. Security Coding Rules

- **Validate all input at the boundary**; treat every external value (HTTP, event, tool arg, LLM output) as untrusted until schema-validated.
- **Tenant scope is mandatory** on every data access; rely on RLS as the enforcing boundary but also filter in the query (defense in depth). No cross-tenant reads, ever.
- **Least privilege:** authz (RBAC + ABAC) checked in the application layer before any effect; tools carry permission scopes and are permission-checked at invocation.
- **No secrets in code, logs, errors, tests, or fixtures.** Secrets resolve via the secrets port at runtime. Fail the build on detected secrets (secret-scanning in CI).
- **No dynamic code execution** (`eval`, `Function`, unvetted `child_process`). Plugin/tool execution is sandboxed against its declared contract.
- **Output encoding & injection safety:** parameterized queries only (no string-built SQL); encode on output; sanitize anything forwarded to n8n / external systems.
- **Dependencies:** pinned via lockfile; CI runs SCA (audit) and blocks known-critical CVEs; new deps require review.
- **AuthN:** OAuth2/OIDC, short-lived JWT access + rotating refresh; mTLS service-to-service. Never hand-roll crypto.
- **PII handling** follows the data-classification in `docs/10-Security-Strategy.md`; log redaction is mandatory (§9).

---

## 17. Documentation Requirements

Per the **[Project Constitution](../README.md)** (binding, non-negotiable):

- **Every module (package and bounded context) has a `README.md`** describing its purpose, public contract (ports/events it exposes), and its layer map.
- **Docs are updated in the same task/PR that changes behavior.** A PR that changes an API, event, schema, or config **without** a corresponding `docs/` and README update fails review (§13).
- Public ports, events, and DTOs carry TSDoc; the "why" lives in an ADR under `docs/adr/` when a non-obvious decision is made.
- OpenAPI/AsyncAPI specs are the source of truth for contracts and are regenerated/verified in CI.

---

## 18. Definition of Done

A change is **Done** only when **all** hold:

1. Meets these coding standards; passes ESLint, Prettier, `tsc`, and dependency-boundary checks with zero errors.
2. Behavior covered by tests at the correct pyramid level; coverage ratchet satisfied; CI green.
3. Multi-tenant, secure, and observable: tenant-scoped, authz-checked, logs/metrics/traces present, no PII/secret leakage.
4. Errors typed and surfaced via `Result`/problem+json; failure paths handled (retry/idempotency/compensation where relevant).
5. Contracts updated (OpenAPI/AsyncAPI) and consistent; events versioned via the outbox.
6. Module `README.md` and affected `docs/` updated in the same PR (constitution).
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
