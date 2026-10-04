# BOOTSTRAP FLOW — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ARCHITECTURE.md` (§6–§7), `PROJECT_STRUCTURE.md`.

---

## 1. Purpose & Scope

This document specifies, step by step, how a single HTTP request becomes a
rendered response in HaHireAI, and how the same machinery is reused for the CLI.
It is the authoritative narrative for the **request lifecycle**
(`ARCHITECTURE.md` §7) built on the **Core Kernel** components (§6).

HaHireAI is a **native PHP 8.3 modular monolith with no framework**
(`PROJECT_CONSTITUTION.md` §5); bootstrapping is therefore a deliberate,
hand-built sequence — not magic. Every stage MUST have one reason to exist and
MUST NOT contain business logic, which lives only in `app/Modules/<Module>/`
(`PROJECT_STRUCTURE.md` §5; `DIRECTORY_STANDARD.md` §5). This is a **design
document**: the pseudo-signatures are illustrative only and marked as such; they
constrain responsibilities and ordering, not code.

---

## 2. Actors & Their Homes

| Stage actor | Location | Namespace |
|---|---|---|
| Front controller | `public/index.php` | — (not namespaced) |
| Bootstrap sequence | `bootstrap/` | — (not namespaced) |
| Environment Loader | `app/Core` | `HaHireAI\Core\…` |
| Configuration Loader | `app/Core` | `HaHireAI\Core\…` |
| Application Kernel | `app/Core` | `HaHireAI\Core\…` |
| Service Container | `app/Core` | `HaHireAI\Core\…` |
| Service Providers | `app/Providers` | `HaHireAI\Providers\…` |
| Module Registry | `app/Core` | `HaHireAI\Core\…` |
| Router + Dispatcher | `app/Core` | `HaHireAI\Core\…` |
| Error Handler / Logger | `app/Core` | `HaHireAI\Core\…` |
| Controllers (Presentation) | `app/Modules/<Module>/Presentation` | `HaHireAI\Modules\<Module>\Presentation\…` |

The Kernel and everything under `app/Core` are runtime plumbing only. They MUST
remain framework-agnostic and free of any module/domain knowledge
(`PROJECT_STRUCTURE.md` §3; `ARCHITECTURE.md` §6).

---

## 3. The Pipeline (ASCII)

```
                         ┌───────────────────────────────────────────────┐
   HTTP request  ─────▶  │  public/index.php   (the ONLY web entrypoint)  │
                         └───────────────────────┬───────────────────────┘
                                                 │ require
                                                 ▼
                         ┌───────────────────────────────────────────────┐
   STAGE 1  BOOTSTRAP    │ bootstrap/                                     │
                         │  • Composer autoload (PSR-4 HaHireAI\ → app/)  │
                         │  • register Error Handler + Logger EARLY       │
                         └───────────────────────┬───────────────────────┘
                                                 ▼
   STAGE 2  ENVIRONMENT  │ Environment Loader — parse .env, validate,     │
                         │  apply defaults (no secrets in repo)           │
                                                 ▼
   STAGE 3  CONFIG       │ Configuration Loader — load /config/*.php into │
                         │  an immutable, read-only config set            │
                                                 ▼
   STAGE 4  KERNEL       │ Application Kernel constructed with Config +    │
                         │  Container (orchestrator; no business logic)   │
                                                 ▼
   STAGE 5  PROVIDERS    │ Service Providers:  register()  →  boot()      │
                         │  (two strict phases, all register before boot) │
                                                 ▼
   STAGE 6  MODULES      │ Module Registry loads enabled modules:         │
                         │  manifests → providers → routes → events       │
                                                 ▼
   STAGE 7  ROUTER       │ Router matches METHOD + path → route +         │
                         │  resolved middleware stack + handler           │
                                                 ▼
   STAGE 8  MIDDLEWARE   │ Middleware pipeline executes (later phases):   │
                         │  session, CSRF, auth, tenant guard, …          │
                                                 ▼
   STAGE 9  CONTROLLER   │ Presentation controller (resolved via Container)│
                         └───────────────────────┬───────────────────────┘
                                                 ▼
                ┌────────────────────────────────────────────────────────┐
   STAGE 10     │ Application use case → Domain → Infrastructure →         │
   DOMAIN FLOW  │ Persistence    (dependency rule: inward/downward only)   │
                └───────────────────────┬────────────────────────────────┘
                                        ▼
   STAGE 11     │ Response built and returned; middleware unwinds;         │
   RESPONSE     │ Kernel terminate hooks run (flush logs, etc.)            │
                └───────────────────────┬────────────────────────────────┘
                                        ▼
                                   HTTP response  ─────▶  client
```

Mapping to `ARCHITECTURE.md` §7: stages 1–6 are *boot*; stages 7–9 are
*route → middleware → controller*; stage 10 is the five-layer descent; stage 11
is *response*.

---

## 4. Stage-by-Stage Responsibilities

### Stage 1 — Front controller (`public/index.php`)

`public/` is the **only** web-exposed directory (`PROJECT_CONSTITUTION.md` §4;
`PROJECT_STRUCTURE.md` §1). `index.php` MUST be thin: it defines the base path,
requires the bootstrap sequence, obtains a booted Kernel, and hands the current
request to it. It MUST contain no configuration, no routing tables, and no
domain code.

```php
// ILLUSTRATIVE ONLY — not an implementation.
$kernel   = require __DIR__ . '/../bootstrap/app.php';   // returns a booted Kernel
$response = $kernel->handle(Request::capture());          // run the pipeline
$response->send();                                        // emit headers + body
$kernel->terminate($response);                            // post-response hooks
```

### Stage 2 — Bootstrap (`bootstrap/`)

The `bootstrap/` directory turns a raw request context into a booted Kernel and
"nothing domain-specific" (`PROJECT_STRUCTURE.md` §3). Its duties, in order:

1. **Autoloading.** Require Composer's autoloader so PSR-4 `HaHireAI\\ → app/`
   is active before any class is referenced (`DIRECTORY_STANDARD.md` §1).
2. **Error & exception handling.** Register the Core **Error Handler** and
   **Logger** *first*, so any failure in later boot stages is captured and
   rendered safely rather than leaking a raw PHP fatal (see §7).
3. **Construct the Kernel.** Wire the Environment Loader, Configuration Loader,
   and Service Container, then build and boot the Application Kernel.

`bootstrap/` MUST NOT hold business logic (`DIRECTORY_STANDARD.md` §5). A
separate `console` entry in `bootstrap/` provides the CLI variant (§6).

### Stage 3 (boot order: Environment) — Environment Loader

Reads `.env`, validates required keys, and applies typed defaults
(`ARCHITECTURE.md` §6). Secrets MUST come from the environment and MUST NOT be
committed (`PROJECT_CONSTITUTION.md` §5, §10). The loader exposes resolved values
to the Configuration Loader only; the rest of the system reads **config**, never
`getenv()` directly. A missing or invalid required variable MUST abort boot with
a clear, non-leaking error (§7).

### Stage 4 (boot order: Configuration) — Configuration Loader

Loads the declarative files in `/config/*.php` (each returns an array;
`DIRECTORY_STANDARD.md` §3) into a single read-only configuration set. Config is
**declarative only** — no logic, no secrets (`PROJECT_STRUCTURE.md` §3). It MAY
read resolved environment values supplied by Stage 3. Constants/globals in code
are forbidden as a substitute for config (`ARCHITECTURE.md` §6). Detailed
semantics live in `CONFIGURATION_GUIDE.md`.

### Stage 5 — Application Kernel construction

The **Application Kernel** is the boot orchestrator (`ARCHITECTURE.md` §6). It is
constructed with the Configuration set and a Service Container instance and
coordinates the remaining boot stages. Its contract is small:

```php
// ILLUSTRATIVE ONLY — names/shape are examples, not a spec.
final class Kernel
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    public function bootstrap(): void;                 // stages 5→6 wiring
    public function handle(Request $request): Response; // stages 7→11
    public function terminate(Response $response): void;// flush/cleanup
}
```

What the Kernel **does**: register/boot providers, trigger module loading,
resolve the router, run the middleware→controller pipeline, return a Response,
and run terminate hooks. What the Kernel **does NOT** do (binding): hold or run
any **business logic**; know about any specific module, entity, or table; act as
a **service locator** for business code; perform persistence or authorization
itself. Authorization happens at the Application boundary and tenant filtering at
Infrastructure (`ARCHITECTURE.md` §8) — never in the Kernel.

### Stage 6 — Service Providers: `register()` then `boot()`

Service Providers (`app/Providers`, `HaHireAI\Providers`) are the *only*
sanctioned place to register bindings and services into the container
(`PROJECT_STRUCTURE.md` §3; `ARCHITECTURE.md` §6). The Kernel runs them in **two
strict phases**:

1. **`register()` — all providers first.** A provider MAY only *add bindings*
   (interface → implementation, singletons, factories). It MUST NOT resolve other
   services, because not every binding exists yet.
2. **`boot()` — after every `register()` has completed.** A provider MAY now
   resolve dependencies and perform wiring that assumes the full binding set
   (e.g. registering event listeners, route groups, health probes).

This two-phase rule removes ordering hazards between providers. Container
mechanics are specified in `SERVICE_CONTAINER.md`.

### Stage 7 (within boot) — Module Registry

The **Module Registry** performs *explicit* module registration and lifecycle
(`ARCHITECTURE.md` §6). Modules are never auto-discovered by scanning business
code arbitrarily; the set of **enabled** modules is explicit, so a module can be
disabled without breaking unrelated ones (`PROJECT_CONSTITUTION.md` §9). For each
enabled module the registry, reading its `module.php` manifest:

1. validates declared **dependencies** (the graph MUST be a DAG — no cycles;
   `ARCHITECTURE.md` §5);
2. registers the module's **Service Provider(s)** into the same two-phase flow;
3. contributes the module's **routes** to the aggregate (`/routes` delegates to
   each module's `Routes/`; `PROJECT_STRUCTURE.md` §4.1);
4. registers the module's **event listeners** with the Event Dispatcher;
5. exposes the module's **`Contracts/`** bindings so other modules can consume
   them synchronously (`ARCHITECTURE.md` §4).

A module's public surface is strictly its `Contracts/` plus published events;
the registry never wires another module to a module's internals
(`ARCHITECTURE.md` §3–§4).

### Stage 8 — Router (match)

The **Router + Dispatcher** matches the request METHOD and path against the
aggregated routes and resolves the target **handler** plus the **middleware**
declared for that route/group (`ARCHITECTURE.md` §6). Supported verbs:
GET/POST/PUT/PATCH/DELETE, with named routes, groups, and path parameters.
Routes are kebab-case (`PROJECT_CONSTITUTION.md` §7). On no match the Router
yields a 404; on a method mismatch, a 405. Full routing rules live in
`ROUTING_GUIDE.md`. **Matching only happens here**; the matched middleware is
*registered* now but **executed in the later phase** (Stage 9).

### Stage 9 — Middleware pipeline (executed)

The middleware resolved in Stage 8 now executes as an ordered, nested pipeline
around the controller. Cross-cutting request concerns belong here — session
start, **CSRF** verification on state-changing requests, authentication, the
**tenant guard** context, and rate limiting (`PROJECT_CONSTITUTION.md` §10;
`ARCHITECTURE.md` §8). Middleware MUST be technical/cross-cutting only; business
rules belong in the Application/Domain layers, not in middleware. Each middleware
MAY short-circuit (e.g. redirect to login, 403 on a failed permission gate) and
return early without reaching the controller.

### Stage 10 — Controller (Presentation)

The matched controller lives in a module's `Presentation/` layer and is
**resolved from the Service Container** with constructor injection
(`SERVICE_CONTAINER.md`). A controller MUST stay thin: translate the HTTP request
into an Application **use case / command / query**, then map the result to a
Response or view-model. Controllers MUST NOT run raw SQL or reach Persistence
directly (`ARCHITECTURE.md` §2 dependency rule).

### Stage 11 — Application → Domain → Infrastructure → Persistence

Inside the use case the five-layer descent applies, dependencies pointing
**inward and downward only** (`ARCHITECTURE.md` §2):

- **Application** orchestrates the use case and enforces the **AuthZ** boundary
  (`ARCHITECTURE.md` §8).
- **Domain** holds entities, value objects, domain services and **contracts**;
  it depends on nothing outward.
- **Infrastructure** *implements* Domain contracts (repositories, adapters) using
  the app-level drivers in `app/Infrastructure` (DB/cache/queue/mail/fs).
- **Persistence** is reached **only** through Infrastructure, using prepared
  statements, with every workspace-scoped query filtered by `workspace_id`
  (`PROJECT_CONSTITUTION.md` §5, §10–§11).

### Stage 12 — Response & terminate

The use-case result becomes a **Response** (HTTP message abstraction;
`ARCHITECTURE.md` §6); all output is escaped on render
(`PROJECT_CONSTITUTION.md` §10). The middleware pipeline then unwinds (outbound
transforms, security headers), `Response::send()` emits headers and body, and the
Kernel's `terminate()` runs post-response hooks (flush logs, dispatch
queue-deferred work). Heavy/AI work MUST NOT run inline — it is queued
(`PROJECT_CONSTITUTION.md` §11; `ARCHITECTURE.md` §8).

---

## 5. What the Kernel Does and Does NOT Do (summary)

| The Kernel DOES | The Kernel does NOT |
|---|---|
| Orchestrate boot: env → config → container → providers → modules | Contain any business/domain logic |
| Register then boot Service Providers in two phases | Know any specific module, entity, or table |
| Trigger Module Registry loading | Read or write the database itself |
| Resolve the Router and run middleware → controller | Perform authorization or tenant filtering itself |
| Build, send, and terminate the Response | Act as a service locator for business code |
| Delegate failures to the Error Handler + Logger | Hold global mutable state or facades |

These prohibitions are binding (`ARCHITECTURE.md` §6;
`PROJECT_CONSTITUTION.md` §4–§6; `DIRECTORY_STANDARD.md` §5).

---

## 6. CLI Bootstrap Variant

The platform ships **no framework-style global binaries**; CLI entrypoints live
in `bootstrap/` (a `console` entry) or `app/Support`
(`PROJECT_STRUCTURE.md` §2). The CLI reuses the *same* boot stages 1–6
(autoload, error handler, environment, configuration, Kernel, providers, module
registry) so that bindings and module wiring are identical to the web path. The
divergence is at the edges:

- **Input:** argv/stdin instead of an HTTP Request; there is **no Router and no
  HTTP middleware** stack. The console resolves a **CLI command** (a module's
  `Presentation/` CLI command) and dispatches it.
- **Output:** exit codes and stdout/stderr instead of an HTTP Response.
- **Context:** session/CSRF/auth middleware do not apply; commands that touch
  workspace-scoped data MUST still establish and respect the tenant context and
  permission checks at the Application boundary (`ARCHITECTURE.md` §8).

Because both entrypoints share one boot sequence, a binding registered by a
provider behaves the same in web and CLI — the design goal of a single Kernel.

---

## 7. Failure Handling During Boot

The Error Handler and Logger are registered **first** (Stage 1.2) precisely so
that boot-time failures are handled, not leaked. Rules (binding):

- The Error Handler MUST distinguish **dev** vs **prod** and MUST **never leak
  stack traces** in production (`ARCHITECTURE.md` §6; `PROJECT_CONSTITUTION.md`
  §10). Dev MAY render diagnostics; prod returns a safe generic error.
- The Logger follows **PSR-3** levels (`debug → critical`)
  (`PROJECT_CONSTITUTION.md` §6).

Expected behaviour per stage:

| Failing stage | Behaviour |
|---|---|
| Autoload (1.1) | Hard fail before handlers exist; surfaced as a minimal safe 500. The window is kept tiny by requiring the autoloader first. |
| Error handler / logger (1.2) | If handler registration itself fails, abort with a minimal safe 500; nothing further can be trusted. |
| Environment (2) | Missing/invalid required `.env` key → abort boot with a clear, non-leaking message; never boot a half-configured app. |
| Configuration (3) | Malformed/missing config file → abort boot (logged at `critical`). |
| Providers register/boot (5) | An exception during `register()`/`boot()` is logged and aborts boot; partial wiring MUST NOT serve traffic. |
| Module Registry (6) | A missing **required** dependency or a dependency **cycle** is a defect and aborts boot (`ARCHITECTURE.md` §5). A disabled **optional** dependency degrades gracefully (`PROJECT_CONSTITUTION.md` §9). |
| Router (8) | No route → 404; method mismatch → 405 (handled, not fatal). |
| Middleware/Controller (9–10) | Exceptions bubble to the global Error Handler, mapped to an appropriate status with a safe body; logged at the correct level. |

A request MUST never be served by a partially booted Kernel: any unrecoverable
boot error stops the pipeline before Stage 8.

---

## 8. Self-Review (Phase 6 gate)

- [ ] `public/index.php` is thin and is the only web entrypoint.
- [ ] Autoload + error handler + logger are registered before any other stage.
- [ ] Environment is loaded and validated before Configuration.
- [ ] Providers run `register()` for all, then `boot()` for all.
- [ ] Module loading is explicit via the Module Registry and manifests.
- [ ] Router only matches; middleware executes in the later phase.
- [ ] The Kernel contains zero business logic and is not a service locator.
- [ ] CLI reuses the same boot stages 1–6.
- [ ] No production path can leak a stack trace.

---

### Related Documents
`ARCHITECTURE.md` · `PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md` ·
`DIRECTORY_STANDARD.md` · `SERVICE_CONTAINER.md` · `ROUTING_GUIDE.md` ·
`CONFIGURATION_GUIDE.md` · `EVENT_BUS.md` · `adr/0001-project-structure.md`
