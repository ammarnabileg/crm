# SERVICE CONTAINER — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ARCHITECTURE.md` (§6), `PROJECT_STRUCTURE.md`.

---

## 1. Purpose & Scope

The **Service Container** is the dependency-injection (DI) core of HaHireAI's
bespoke Core Kernel (`ARCHITECTURE.md` §6). It binds **interfaces to
implementations** and constructs object graphs so that modules depend on
**contracts, not concretions** (`ARCHITECTURE.md` §5;
`PROJECT_CONSTITUTION.md` §6).

This is a **design document** for a *small, purpose-built* container. HaHireAI
uses **native PHP 8.3 with no framework** (`PROJECT_CONSTITUTION.md` §5);
consequently the container is **bespoke** — we MUST NOT pull in an off-the-shelf
DI library. "Every component must earn its place" (`ARCHITECTURE.md` §6), so the
feature set below is deliberately minimal. Anything not listed in §3 is out of
scope (§7).

Lives at `app/Core` (`HaHireAI\Core`), constructed during boot and handed to the
Application Kernel (`BOOTSTRAP_FLOW.md` §4, Stage 5). The pseudo-signatures here
are **illustrative only** and clearly marked; they constrain intent, not code.

---

## 2. Where It Sits

```
          register bindings                 resolve graphs
Providers ─────────────────▶  Container  ◀───────────────── Kernel / Router
(app/Providers)                (app/Core)                    (controller resolution)
        ▲                          │
        │ expose Contracts         │ interface → implementation
        │                          ▼
   Modules' Contracts/   ◀────  bound here so other modules consume them
```

- **Service Providers** (`app/Providers`, `HaHireAI\Providers`) are the only
  place bindings are registered (`PROJECT_STRUCTURE.md` §3).
- **Modules** expose behaviour through their `Contracts/` namespace; the
  container binds each contract to its implementation at boot
  (`ARCHITECTURE.md` §4).
- The **Kernel/Router** resolve controllers and their dependencies from the
  container (`BOOTSTRAP_FLOW.md` §4, Stage 9–10).

---

## 3. Supported Features (the ONLY ones)

The container supports **exactly** the following capabilities. No more.

### 3.1 Binding interfaces → implementations

A binding maps an abstract identifier (normally a `…\Contracts\` interface) to a
concrete implementation or a factory closure. Business code type-hints the
**interface**; the container supplies the bound concretion. This is the
mechanism behind "modules depend on contracts, not implementations; the container
binds interface → implementation at boot" (`ARCHITECTURE.md` §5).

```php
// ILLUSTRATIVE ONLY — shape is an example, not a prescribed API.
$container->bind(JobRepository::class, MySqlJobRepository::class);
$container->bind(Clock::class, fn (Container $c) => new SystemClock());
```

### 3.2 Singleton vs transient lifetimes

Two lifetimes, and only two:

- **Transient** (default): a **new instance per resolution**. Use for
  lightweight, stateless collaborators.
- **Singleton**: **one shared instance** per container lifetime, created lazily
  on first resolution. Use for things that are expensive or must be shared
  (e.g. a DB connection manager, the Logger). Required by `ARCHITECTURE.md` §6
  ("singleton/transient bindings").

Singletons MUST be safe to share within a single request/CLI invocation. Global
**mutable** state is forbidden (`PROJECT_CONSTITUTION.md` §6); a singleton is a
scoped shared instance, not a global variable.

```php
// ILLUSTRATIVE ONLY.
$container->singleton(Logger::class, FileLogger::class);
$container->bind(PasswordHasher::class, Argon2idHasher::class); // transient
```

### 3.3 Constructor injection / autowiring

The container resolves a class by reading its **constructor signature** via
reflection and recursively resolving each typed parameter. This is the **only**
injection style supported — there is no property or setter injection. Untyped or
unresolvable parameters MUST raise a clear container exception rather than
guessing (e.g. scalars without a contextual binding; see §3.5).

```php
// ILLUSTRATIVE ONLY — autowiring resolves the dependencies of:
final class PublishJob
{
    public function __construct(
        private readonly JobRepository $jobs,   // bound interface → impl
        private readonly Clock $clock,          // autowired
    ) {}
}
$useCase = $container->make(PublishJob::class); // graph built automatically
```

### 3.4 Lazy resolution

Nothing is instantiated until it is **resolved**. Bindings registered in a
provider's `register()` phase are mere recipes; the object is built on first
`make()`/`get()` (`BOOTSTRAP_FLOW.md` §4, Stage 5). Singletons are likewise
created lazily and then cached. Where construction must be deferred *past* graph
build (e.g. to break a benign initialization order), a binding MAY be a factory
closure or a lightweight lazy proxy that resolves the real instance on first use.
Lazy resolution keeps boot cheap and aligns with the performance budgets in
`PROJECT_CONSTITUTION.md` §11.

### 3.5 Contextual bindings

The same abstract MAY resolve to **different** concretions depending on the
**consumer**. This covers cases such as two implementations of one contract, or
injecting a specific scalar/config value into a single class.

```php
// ILLUSTRATIVE ONLY.
$container->when(ResumeStorage::class)
          ->needs(Filesystem::class)
          ->give(S3Filesystem::class);

$container->when(ExportReport::class)
          ->needs('$chunkSize')
          ->give(fn (Container $c) => $c->config('reports.chunk_size'));
```

Contextual bindings are the sanctioned way to satisfy ambiguity and scalar
parameters; they keep autowiring deterministic without resorting to a service
locator (§6).

---

## 4. How Service Providers Register Bindings

Providers are the **only** registration surface (`PROJECT_STRUCTURE.md` §3) and
participate in the Kernel's strict two-phase boot (`BOOTSTRAP_FLOW.md` §4,
Stage 5):

1. **`register()`** — declare bindings **only**. A provider MUST NOT resolve
   services here, because not every binding exists yet.
2. **`boot()`** — runs after *all* providers have registered; the full binding
   set is available, so a provider MAY resolve dependencies and perform wiring.

```php
// ILLUSTRATIVE ONLY — provider shape under app/Providers.
final class JobsServiceProvider extends ServiceProvider
{
    public function register(Container $c): void
    {
        $c->singleton(JobService::class, DefaultJobService::class);
        $c->bind(JobRepository::class, MySqlJobRepository::class);
    }

    public function boot(Container $c): void
    {
        // safe to resolve here — all bindings registered.
        $c->get(EventDispatcher::class)->subscribe(/* … */);
    }
}
```

Naming follows `PROJECT_CONSTITUTION.md` §7: public contracts are PascalCase with
**no suffix** in `…\Contracts\`; implementations are technology/role-prefixed
(`MySqlJobRepository`, `DefaultJobService`).

---

## 5. How Modules Expose & Consume Contracts via the Container

A module's **public surface is its `Contracts/` namespace plus its events** —
nothing else (`ARCHITECTURE.md` §3; `PROJECT_CONSTITUTION.md` §7).

- **Expose:** the owning module's provider binds its public contract to its
  internal implementation. Only the **interface** is referenced by outsiders; the
  concrete class stays internal (`DIRECTORY_STANDARD.md` §4).
- **Consume (synchronous):** a consuming module type-hints the *other* module's
  published contract in its constructor; the container injects the bound
  implementation (`ARCHITECTURE.md` §4, channel 1). A module MUST NOT type-hint
  or reference another module's internal `Domain/`, `Application/`, or
  `Infrastructure/` classes.
- **Decoupled alternative:** where no immediate answer is needed, modules
  communicate via the **Event Dispatcher** instead of a contract
  (`ARCHITECTURE.md` §4, channel 2; `EVENT_BUS.md`).

Because consumers bind to interfaces, a module can later be extracted to a
separate service behind the same contract without changing its consumers
(`ARCHITECTURE.md` §9). Cross-module dependencies MUST remain a **DAG** — the
container does not resolve circular constructor dependencies, and cycles are a
defect (`ARCHITECTURE.md` §5).

---

## 6. The Rule: No Service Locator Inside Business Logic

**Binding rule (`ARCHITECTURE.md` §6; `PROJECT_CONSTITUTION.md` §6): business
code MUST declare its dependencies as constructor parameters and let the
container inject them. It MUST NOT receive the container and pull services out of
it.**

```php
// ANTI-PATTERN — DO NOT DO THIS (service locator in business logic).
final class PublishJob
{
    public function __construct(private Container $c) {}     // ❌
    public function handle(): void
    {
        $repo = $this->c->get(JobRepository::class);        // ❌ hidden dependency
    }
}

// CORRECT — dependencies are explicit and testable (see §3.3).
final class PublishJob
{
    public function __construct(private readonly JobRepository $jobs) {} // ✅
}
```

Rationale: injected dependencies are explicit, type-checked, and trivially
substitutable in tests (`PROJECT_CONSTITUTION.md` §13). The container therefore
appears **only** at the composition edges — Service Providers and the
Kernel/Router resolving entrypoints — never threaded into Domain/Application
code.

---

## 7. Deliberately Out of Scope

To keep the kernel minimal (`ARCHITECTURE.md` §6, "every component must earn its
place"), the container **MUST NOT** grow the following:

- **No facades.** Static accessor shims over container services are forbidden
  (`ARCHITECTURE.md` §6: "no facades").
- **No global helpers / service locator.** No `app()`, `resolve()`, or global
  accessor functions; resolution happens at the edges only (§6).
- **No auto-discovery of bindings by scanning business code.** Bindings are
  explicit in providers; modules are explicit in the Module Registry
  (`BOOTSTRAP_FLOW.md` §4, Stage 6).
- **No setter/property injection, no attribute-driven autowiring.** Constructor
  injection only (§3.3).
- **No extra lifetimes/scopes** beyond singleton and transient (e.g. no
  per-request pools, no thread scopes).
- **No method/parameter interception, no compiled/cached container, no tagged
  collections, no circular-dependency resolution.** These add complexity the
  product does not need today and would mask design problems.
- **No third-party DI library.** The container is bespoke by mandate
  (`PROJECT_CONSTITUTION.md` §5).

If a future need is real, it is introduced via the amendment + ADR process
(`PROJECT_CONSTITUTION.md` §16), not by quietly over-engineering the container.

---

## 8. Failure & Diagnostics

- **Unresolvable dependency** (untyped/scalar parameter without a contextual
  binding, or an unbound abstract with no concretion): raise a **clear container
  exception** naming the culprit class and parameter. Never silently inject
  `null`.
- **Missing binding** for an interface that has no autowirable concretion: fail
  fast at resolution.
- **Circular dependency:** detected during graph construction and reported as a
  defect (`ARCHITECTURE.md` §5) — the container MUST NOT loop indefinitely.
- All such failures are surfaced through the Core **Error Handler** and
  **Logger** (`BOOTSTRAP_FLOW.md` §7); in production they MUST NOT leak stack
  traces (`PROJECT_CONSTITUTION.md` §10).

---

## 9. Self-Review (Phase 6 gate)

- [ ] Only the §3 features exist; nothing from §7 has crept in.
- [ ] The container is bespoke (no third-party DI library).
- [ ] Bindings are registered only in Service Providers, in `register()`.
- [ ] Constructor injection is the sole injection style.
- [ ] Singletons are scoped shared instances, not global mutable state.
- [ ] Business logic never receives or queries the container (no locator).
- [ ] Modules consume each other only via published `Contracts/`.
- [ ] Unresolvable/circular dependencies fail fast with a clear, safe error.

---

### Related Documents
`ARCHITECTURE.md` · `PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md` ·
`DIRECTORY_STANDARD.md` · `BOOTSTRAP_FLOW.md` · `ROUTING_GUIDE.md` ·
`CONFIGURATION_GUIDE.md` · `EVENT_BUS.md` · `adr/0001-project-structure.md`
