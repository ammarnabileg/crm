# ROUTING GUIDE — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ARCHITECTURE.md`, `PROJECT_STRUCTURE.md`, `API_GUIDELINES.md`.

---

## 0. Purpose & Scope

This document designs the **routing system** of HaHireAI: how an incoming HTTP
request is matched to the controller that handles it, and how every module
declares its routes without touching another. It is **design on paper**; the
runtime lives in the **Router + Dispatcher** of the Core Kernel
(`ARCHITECTURE.md` §6, Phase 7). Illustrative code is **EXAMPLE ONLY**, not source.

This guide defers to `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`,
`PROJECT_STRUCTURE.md`, and `API_GUIDELINES.md`; on conflict those win
(Constitution §0). External REST policy (versioning, error envelope, auth,
pagination) is owned by `API_GUIDELINES.md` — this guide governs only how those
endpoints are *wired*. **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**
per [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

## 1. Design Principles

- **No complex DSL.** Routing is a thin, explicit map from `(method, path)` to a
  controller action. We do **not** build a fluent macro language, route
  annotations, or controller auto-discovery. A reader **MUST** be able to find
  the handler for any URL by reading a route file.
- **Modules own their routes.** Every module declares its own routes under its
  `Routes/` directory (`PROJECT_STRUCTURE.md` §4). The root `/routes` directory
  only **aggregates** them; it contains no feature routes of its own.
- **Additive registration.** Adding a module **MUST NOT** require editing another
  module's route files (`ARCHITECTURE.md` §9). Registration is discovery-driven
  via the Module Registry.
- **Single front controller.** Only `public/index.php` is web-exposed
  (Constitution §4); all routing flows through it.
- **Routes are declarations, not logic.** A route file maps URLs to handlers and
  middleware. It **MUST NOT** contain business logic, queries, or rendering.

## 2. Supported HTTP Verbs

The Router **MUST** support exactly these verbs, each with standard semantics
(`API_GUIDELINES.md` §B1):

| Verb | Semantics | Safe | Idempotent |
|---|---|---|---|
| `GET` | Read a resource or render a page. **MUST NOT** mutate state. | yes | yes |
| `POST` | Create, or trigger a non-idempotent action. | no | no |
| `PUT` | Full replacement of a resource. | no | yes |
| `PATCH` | Partial update of a resource. | no | no |
| `DELETE` | Remove a resource. | no | yes |

- `HEAD` and `OPTIONS` are handled by the Kernel generically (HEAD mirrors GET
  without a body; OPTIONS reports allowed methods). Modules **SHOULD NOT** declare
  them by hand.
- A registration for any other verb is a defect.
- State-changing verbs (`POST`/`PUT`/`PATCH`/`DELETE`) on **web** routes
  **MUST** be guarded by CSRF middleware (Constitution §10; §7 below). The **API**
  surface is token-authenticated and stateless (`API_GUIDELINES.md` §B9).

## 3. Route Registration

### 3.1 Per-module declaration

Each module ships route files in `app/Modules/<Module>/Routes/`. By convention:

- `web.php` — server-rendered, session/CSRF-protected web routes.
- `api.php` — external REST endpoints mounted under `/api/v1` (Phase 13 wires the
  gateway; the declarations exist from each module's foundation).

Route files are **kebab/lowercase `.php`** files that **return a closure** taking
the route collector (`DIRECTORY_STANDARD.md` §3). They register routes against
the collector; they **MUST NOT** instantiate controllers or the kernel.

### 3.2 Aggregation by `/routes`

The root `/routes` directory holds the **global registration** loaded at boot
(`PROJECT_STRUCTURE.md` §3). Its `web.php` opens the web group and its `api.php`
opens the `/api/v1` group; both iterate the **enabled** modules from the **Module
Registry** (`ARCHITECTURE.md` §6) and pull in each module's `Routes/web.php` and
`Routes/api.php`. Because aggregation is registry-driven, a **disabled** module
contributes no routes and degrades gracefully (Constitution §9); `module.php`
(which declares the module's routes — `PROJECT_STRUCTURE.md` §4) governs
eligibility.

### 3.3 Collision policy

Two routes that resolve the same `(method, path)` are a **defect**. The loader
**MUST** fail fast at boot with a clear message naming both modules, rather than
silently letting later registration win. Named-route collisions (§5) are treated
the same way.

## 4. Route Groups

Groups apply shared attributes — a **path prefix**, a **middleware stack**, and a
**name prefix** — to a set of routes, and **MAY** nest. Groups are how the two
surfaces are organized and how cross-cutting concerns attach without repetition.

- **Web group:** root prefix `/`, web middleware (session, CSRF, locale), name
  prefix per module (e.g. `jobs.`).
- **API group:** prefix `/api/v1`, API middleware (token auth, rate limit, JSON),
  name prefix `api.v1.`.
- Nested groups **compose**: prefixes concatenate, middleware stacks append
  (outer runs first), name prefixes concatenate.

> **EXAMPLE — nesting (illustrative only):** an API group `/api/v1` containing a
> module group `/jobs` produces base path `/api/v1/jobs`, the API middleware
> stack plus any module middleware, and name prefix `api.v1.jobs.`.

## 5. URL Parameters

- Dynamic segments are written in braces: `/jobs/{jobId}` (camelCase token name).
- Path identifiers are **ULIDs** unless stated otherwise (`API_GUIDELINES.md`
  §B3; `DATABASE_ARCHITECTURE.md`). A route **MAY** constrain a parameter with a
  simple, documented pattern; constraints stay minimal — deep validation belongs
  in the Application layer, not the router.
- Matched parameters are passed to the controller action as typed arguments by
  the Dispatcher; controllers **MUST NOT** read the raw path themselves.
- **Nesting is shallow.** Express ownership at most one level deep
  (`/jobs/{jobId}/applications`); beyond that, filter on the top-level collection
  (`API_GUIDELINES.md` §B3). Optional segments **SHOULD** be avoided — prefer two
  explicit routes over one ambiguous pattern.

## 6. Named Routes & URL Generation

- Every route **SHOULD** carry a **stable name** = module prefix + action
  (`jobs.show`, `api.v1.applications.create`). Names are the **only** sanctioned
  way to refer to a route elsewhere.
- The Kernel exposes a **URL generator** that builds paths from a name plus
  parameters. Code, views, and redirects **MUST** generate URLs by name and
  **MUST NOT** hard-code path strings — the routing corollary of "no constants in
  code" (`CONFIGURATION_GUIDE.md`). Hard-coded paths break silently when a URL
  changes; named generation fails loudly on a missing/extra parameter or unknown
  name.
- Absolute-URL generation uses the configured app base URL from the Config loader
  (`CONFIGURATION_GUIDE.md`), never a hard-coded host.

## 7. Middleware Registration

Middleware is the pipeline that wraps a matched route before/after the controller
runs. In **this phase middleware is declared, not executed** — the registration
surface and ordering are designed now; execution of auth, CSRF, rate limiting,
and tenant resolution lands in later phases (`ARCHITECTURE.md` §6–§8).

- Middleware is attached at three scopes: **global** (every request), **group**
  (a route group), and **route** (one route). The effective stack is the
  concatenation in that order; the response unwinds in reverse.
- Middleware is referenced by a **name/alias** resolved from the container
  (`SERVICE_CONTAINER.md`), never by hard-coded class strings in the route file.
- Reserved names are designed up front so route files can declare intent today —
  web: `session`, `csrf`, `locale`, `auth.web`; API: `auth.token`, `rate-limit`,
  `json`, `idempotency` (`API_GUIDELINES.md` §B6, §B8, §B9); both:
  `permission:<key>` binding a route to a **permission key** (deny-by-default,
  never a role — Constitution §4; `PERMISSION_MODEL.md`).
- **Authorization placement.** Permission checks belong at the **Application
  boundary** (`ARCHITECTURE.md` §8); route-level `permission:` middleware is a
  first gate, not a replacement for the use-case check.
- **Tenant resolution.** Requests resolve to a workspace via middleware; all
  downstream data access is workspace-scoped (Constitution §5;
  `WORKSPACE_MODEL.md`). Declared now, enforced in its phase.

## 8. Web Routes vs API Routes

The two surfaces are organized separately and **MUST NOT** be mixed in one file.

| Aspect | Web routes (`web.php`) | API routes (`api.php`) |
|---|---|---|
| Base path | `/` (kebab-case) | `/api/v1` |
| Auth | Browser **session** cookie | **Bearer token** only (no sessions) |
| State-change guard | **CSRF** token required | Token + idempotency key |
| Response | Server-rendered HTML (Presentation) | JSON envelope (`API_GUIDELINES.md` §B7) |
| Versioning | None (UI evolves freely) | URI-versioned `/api/v1` |
| Owner doc | This guide + `UI_GUIDELINES.md` | `API_GUIDELINES.md` (Part B) |

- The **session vs token** split is binding: the REST API **NEVER** uses sessions
  (`API_GUIDELINES.md` §B9; Constitution §10).
- API resource paths are **kebab-case, plural nouns** (`/api/v1/job-postings`);
  web paths are likewise **kebab-case** (Constitution §7). The full naming
  authority for API resources is `API_GUIDELINES.md` §B3.

## 9. kebab-case URL Convention

- All URL path segments **MUST** be **kebab-case** (Constitution §7):
  `/jobs/active-postings`, `/api/v1/job-postings`. Underscores, camelCase, and
  spaces in paths are forbidden.
- This is **path** casing only. Route **parameter tokens** (`{jobId}`) and route
  **names** (`jobs.active-postings`) are identifiers, not URL text; the kebab rule
  governs the emitted URL.
- Query-string keys follow `API_GUIDELINES.md` (e.g. `?job-id=...`); they
  **SHOULD** stay consistent but are not governed by the path rule.

## 10. 404 / 405 Handling

The Dispatcher distinguishes "no such path" from "wrong method on a known path":

- **No route matches the path → `404 Not Found`** — JSON error envelope for the
  API (`API_GUIDELINES.md` §B7), the standard not-found view for the web. Out-of-
  workspace resources also return `404` (never `403`) to avoid leaking existence
  (`API_GUIDELINES.md` §B9).
- **Path matches but the method does not → `405 Method Not Allowed`,** with an
  `Allow` header listing the supported methods. `OPTIONS` on a known path returns
  `204`/`200` with the same `Allow` header.
- Unhandled exceptions are caught by the global **Error Handler**
  (`ARCHITECTURE.md` §6), which renders safe output and **MUST NOT** leak stack
  traces, SQL, or paths in production (Constitution §10). Every error response
  carries a correlation `request_id` (`API_GUIDELINES.md` §B7; `OBSERVABILITY.md`).

## 11. Illustrative Route Declaration

Routing sits inside the request lifecycle (`ARCHITECTURE.md` §7): the Kernel boots,
`/routes` aggregates each enabled module's `Routes/{web,api}.php`, then
`Router.match(method, path)` resolves to a 404 (no path), 405 + `Allow` (wrong
method), or the middleware pipeline → controller.

> **EXAMPLE ONLY — illustrative, not source.** The intended shape of a module
> route file: a returned closure registering routes, a group with
> prefix/name/middleware, URL parameters, named routes, and **declared**
> middleware. Final signatures are fixed when the Router lands (Phase 7).

```php
<?php
// EXAMPLE ONLY — app/Modules/Jobs/Routes/web.php  (not source)
declare(strict_types=1);

use HaHireAI\Core\Routing\RouteCollector;          // illustrative
use HaHireAI\Modules\Jobs\Presentation\JobController;

return static function (RouteCollector $routes): void {
    // Group: shared prefix, name prefix, and (declared) middleware stack.
    $routes->group('/jobs', 'jobs.', ['session', 'locale', 'auth.web'],
        static function (RouteCollector $r): void {
            $r->get('/', [JobController::class, 'index'])->name('index');
            // kebab-case URL:
            $r->get('/active-postings', [JobController::class, 'active'])->name('active-postings');
            // URL parameter passed to the action:
            $r->get('/{jobId}', [JobController::class, 'show'])->name('show');
            // state-changing: CSRF + permission declared now, executed later:
            $r->post('/', [JobController::class, 'store'])
              ->name('store')->middleware(['csrf', 'permission:job.create']);
            $r->patch('/{jobId}', [JobController::class, 'update'])
              ->name('update')->middleware(['csrf', 'permission:job.update']);
        }
    );
};
```

> **EXAMPLE ONLY — URL generation by name (illustrative):** build paths from the
> route **name**, never a literal — `url('jobs.show', ['jobId' => $id])` →
> `/jobs/01J...`. The matching API file (`Routes/api.php`) mounts under the
> `/api/v1` group with `auth.token` + `rate-limit` and returns the JSON envelope.

## 12. Self-Review (Phase 6 gate)

- [ ] Every URL path segment is kebab-case; modules declare routes under `Routes/`
      and `/routes` only aggregates from the Module Registry.
- [ ] Adding a module wires its routes without editing another module's files.
- [ ] Web routes use sessions + CSRF; API routes use tokens (never sessions);
      state-changing/sensitive routes declare CSRF and a `permission:<key>`.
- [ ] Cross-references use route **names**, never hard-coded paths.
- [ ] 404 vs 405 are distinguished; 405 returns an `Allow` header; errors never
      leak internals.

---

### Related Documents
`ARCHITECTURE.md` · `PROJECT_STRUCTURE.md` · `API_GUIDELINES.md` ·
`PROJECT_CONSTITUTION.md` · `DIRECTORY_STANDARD.md` · `SERVICE_CONTAINER.md` ·
`CONFIGURATION_GUIDE.md` · `BOOTSTRAP_FLOW.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `UI_GUIDELINES.md` · `OBSERVABILITY.md`
