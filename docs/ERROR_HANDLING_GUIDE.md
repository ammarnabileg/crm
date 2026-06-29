# ERROR HANDLING GUIDE — HaHireAI

> **Status:** Adopted (Phase 6) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ARCHITECTURE.md` (§6), `CODING_STANDARD.md`. **Tracking:** Phase 15 (`ERROR_TRACKING.md`).

---

## 0. Purpose & Scope

This guide specifies how HaHireAI **handles failure**: how throwables are caught
centrally, classified, mapped to HTTP, logged, surfaced to users and API clients,
and recovered from. It elaborates the Core Kernel **Error Handler**
(`ARCHITECTURE.md` §6) and the error rules in `CODING_STANDARD.md` §7 into a
single, binding design. It is **design documentation, not implementation**; code
fragments are **illustrative only** and are not source files.

**Supremacy.** This guide defers to `ARCHITECTURE.md` and the
`PROJECT_CONSTITUTION.md`; on any conflict, those win. Interpretation keywords
(**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow
[RFC 2119](https://www.rfc-editor.org/rfc/rfc2119); a **MUST / MUST NOT** rule is
binding and a violation is a defect that blocks merge.

**The cardinal rule.** In **production**, HaHireAI **MUST NEVER** leak a stack
trace, SQL, file path, internal class name, or secret to a user or an API client
(`ARCHITECTURE.md` §6; `CODING_STANDARD.md` §7; `SECURITY_GUIDE.md` §6).
Everything below serves that rule while keeping the platform debuggable for
operators.

---

## 1. The Global Exception Handler

A single **global handler**, installed by the Core Kernel during bootstrap
(`ARCHITECTURE.md` §7, *bootstrap → error handler*; `Core_Kernel.md` §2), is the
**last line of defense**. It registers the PHP exception, error, and shutdown
handlers so that **no** unhandled throwable — and no fatal error — escapes
unformatted. Its responsibilities:

- **Catch every uncaught throwable** at the top of the request lifecycle and
  convert it into a controlled **Response** (`Core_Kernel.md` §4); **promote** PHP
  errors/warnings/notices into typed throwables so they flow through the same path
  (`strict_types=1` — `CODING_STANDARD.md` §1); and **catch fatal/shutdown errors**
  to render a safe response rather than a blank page.
- **Classify** the throwable (§4), **map** it to an HTTP status (§5), **log** it
  with safe context (§6), and **render** the right surface (§7) — HTML page or API
  envelope, dev or prod.
- **Emit `kernel.exception.captured`** on the Event Bus (`Core_Kernel.md` §7) so
  Observability can react (`OBSERVABILITY.md`, `ERROR_TRACKING.md`) without the
  kernel knowing about monitoring.

The handler contains **no business logic** and **no module knowledge**
(`PROJECT_STRUCTURE.md` §5); it is cross-cutting plumbing, depending on the PSR-3
**Logger** and the **Configuration** loader by injection, never a service locator
(`CODING_STANDARD.md` §6).

> **EXAMPLE — illustrative handler shape, not implementation:**
> ```php
> // declare(strict_types=1); namespace HaHireAI\Core\Errors;
> public function handle(\Throwable $e, Request $request): Response
> {
>     $mapped = $this->taxonomy->classify($e);     // §4
>     $this->logger->log($mapped->logLevel(), $mapped->safeMessage(), [
>         'request_id' => $request->correlationId(), // §8
>         'exception'  => $e::class,
>         // NEVER: request body, passwords, tokens, full PII — §6
>     ]);
>     $this->events->dispatch(new ExceptionCaptured($mapped, $request));
>     return $this->renderer->render($mapped, $request); // §7 (dev vs prod)
> }
> ```

---

## 2. Development vs Production Modes

The handler behaves differently by environment, selected from configuration/
environment (`ARCHITECTURE.md` §6; `CODING_STANDARD.md` §7) — never hard-coded.

| Aspect | **Development** | **Production / Staging** |
|---|---|---|
| Detail shown to user | Full diagnostics: message, type, **stack trace**, file/line, request context. | **None.** A friendly, generic error page/envelope only (§7). |
| Stack traces | Rendered to aid debugging. | **NEVER rendered to a user/client.** Captured in logs only (§6). |
| Secrets / SQL / paths | May appear in the dev trace (local, disposable data). | **MUST NOT** appear anywhere user-visible. |
| Logging | Verbose; debug level available. | Structured, level-appropriate; trace in logs, not on screen. |
| `request_id` shown | Optional. | **Shown** to the user so they can quote it to support (§8). |

Binding rules:

- The mode is read from the environment (`APP_ENV` / debug flag pattern). It
  **MUST** default to **production-safe** behavior; a missing or unknown setting
  is treated as production (fail closed), never as development.
- **Staging behaves like production** for error leakage (`DEPLOYMENT_GUIDE.md`
  §1): no stack traces to users, because staging is prod-like and may be
  network-reachable.
- A development-only detailed error page **MUST NOT** be reachable in production —
  not via a query flag, header, or cookie. Detail is an environment property, not
  a per-request toggle.

---

## 3. Throwing Discipline (how code raises errors)

The handler is the safety net; correct **throwing** at the source is the first
control. This restates `CODING_STANDARD.md` §7 as it pertains to handling.

- **Exceptions for the exceptional.** Broken invariants, programming errors, and
  infrastructure failures **throw** specific exception classes — never bare
  `\Exception` / `\RuntimeException` for domain meaning. **Expected domain
  outcomes are modeled, not thrown:** ordinary results a caller handles (validation
  failed, transition not allowed, quota exceeded) **SHOULD** use a typed **Result**
  (`/shared`) or a narrow domain exception, not an exception as control flow.
- **Wrap, don't leak, infrastructure errors.** Infrastructure exceptions (e.g.
  PDO) **MUST** be wrapped into a module/domain exception at the Infrastructure
  boundary; raw driver exceptions **MUST NOT** propagate upward, keeping SQL and
  connection detail out of upper layers and out of any leaked output.
- **Never swallow.** Empty `catch` blocks are forbidden; a catch handles,
  translates, or logs-and-rethrows (`CODING_STANDARD.md` §7, §10 *Silent failure*).
  Each module owns its exception types in `Domain/`, carrying domain meaning, not
  transport concerns.

---

## 4. Exception Taxonomy

Every throwable resolves to exactly one **category**, which determines its HTTP
status (§5), default log level (§6), and whether it is "expected" (client-caused)
or "unexpected" (server-caused). Categories are derived from a small set of
**base exception types** that modules extend.

| Category | Nature | Examples | Expected? |
|---|---|---|---|
| **Validation** | Well-formed request, invalid data. | Bad email, missing required field, value out of range. | Yes (client) |
| **Domain / Business-rule** | A business invariant or transition is violated. | Illegal pipeline stage move, quota exceeded, duplicate key conflict. | Yes (client) |
| **Authentication** | Caller is not (validly) authenticated. | Missing/expired session or token, failed login. | Yes (client) |
| **Authorization** | Authenticated but **permission denied** (deny-by-default). | No `candidate.export`; acting outside the Platform Context. | Yes (client) |
| **Not-Found** | Resource absent **or outside the caller's workspace**. | Unknown id; cross-tenant resource (returned as 404, not 403 — §5, §9). | Yes (client) |
| **Infrastructure** | A dependency failed. | DB unreachable, mail transport down, AI provider timeout, cache failure. | No (server) |
| **Unexpected / Programming** | A bug or broken invariant in our code. | Type error, null where impossible, unhandled state. | No (server) |

- **Client-caused** categories are **safe to describe** to the caller (a safe,
  generic message — never internal detail) and are logged at lower levels (§6).
- **Server-caused** categories are **never described** to the caller beyond a
  generic message + `request_id`, and are logged at `error`/`critical` (§6).
- A category maps to a **stable, machine-readable `code`** for the API envelope
  (e.g. `validation_failed`, `not_found`, `permission_denied`,
  `internal_error` — `API_GUIDELINES.md` §B7).

---

## 5. HTTP Status Mapping

The handler maps each category to a **standard HTTP status** (`API_GUIDELINES.md`
§B4); we never invent meanings.

| Category | HTTP status | API `code` (example) |
|---|---|---|
| Malformed syntax / bad params | **400** Bad Request | `bad_request` |
| Authentication | **401** Unauthorized | `unauthenticated` |
| Authorization (permission denied) | **403** Forbidden | `permission_denied` |
| Not-Found (incl. cross-tenant) | **404** Not Found | `not_found` |
| Domain conflict / state clash | **409** Conflict | `conflict` |
| Validation (well-formed, invalid data) | **422** Unprocessable Entity | `validation_failed` |
| Infrastructure / Unexpected / Programming | **500** Internal Server Error | `internal_error` |

Binding rules:

- **Cross-tenant access returns `404`, not `403`** — a resource outside the
  caller's workspace MUST be indistinguishable from a non-existent one, to avoid
  leaking existence (`SECURITY_GUIDE.md` §4; `API_GUIDELINES.md` §B4, §B9). This is
  a tenant-isolation rule, not a stylistic one.
- **`401` vs `403`:** `401` = *who are you?* (no/invalid credentials); `403` =
  *you may not* (authenticated, permission denied, deny-by-default —
  `SECURITY_GUIDE.md` §3).
- **Validation = `422`** (well-formed but invalid); reserve **`400`** for
  genuinely malformed syntax/parameters.
- **`429` Too Many Requests** is produced by the rate limiter
  (`API_GUIDELINES.md` §B8) and flows through the same handler/envelope.
- A **500 MUST carry no internals** — generic message + `request_id` only (§0, §7).

---

## 6. Logging of Exceptions

Every captured throwable is logged through the **PSR-3 Logger**
(`ARCHITECTURE.md` §6; `CODING_STANDARD.md` §1 PSR-3) — never `error_log()`,
`echo`, or a per-module log file (the shared Logger is the single source —
`Observability.md` §2).

**Level mapping** (PSR-3 `debug → critical`):

| Category | Default level |
|---|---|
| Validation, Domain, Not-Found | `info` / `notice` (expected, client-caused) |
| Authentication, Authorization | `warning` (security-relevant; spikes feed the Security Center) |
| Infrastructure | `error` |
| Unexpected / Programming | `critical` |

**What is captured (structured context):** the exception **type/class** and a
**safe message**; the full **stack trace** (in the **log only**, never in user
output — §0, §2); the correlation **`request_id`** (§8); **actor identity** as an
**id reference** (user id), the **workspace_id**, and the **request route/method**
— enough to reproduce and attribute; and the mapped **category**, **HTTP status**,
and **`code`**.

**What MUST NEVER be logged** (`CODING_STANDARD.md` §7; `SECURITY_GUIDE.md` §6,
§10): passwords, tokens, API keys, signing secrets, or session identifiers; **raw
request bodies** or full PII payloads (candidate/employee personal data — log
**ids**, not content); connection strings, decrypted secrets, or anything secret
from the environment.

> Logs are operator-facing telemetry; they MUST be safe to read and to ship to the
> Phase 15 **Log Explorer** without exposing secrets or PII (`Observability.md`
> §2). When in doubt, log an id, not the value.

---

## 7. Error Pages & API Responses

The handler renders the correct **surface** for the caller — a server-rendered
HTML page for the browser app, or the JSON **error envelope** for the API
(`API_GUIDELINES.md` §B7) — selected by content negotiation (`Accept` / route).

### 7.1 HTML error pages (browser app)

Friendly, branded, bilingual **AR/EN** (`UI_GUIDELINES.md`) pages that explain
the situation without internal detail and offer a way forward (home, retry,
contact). Mandatory pages:

| Page | When |
|---|---|
| **404** | Resource not found or outside the caller's workspace (§5). |
| **403** | Authenticated but not permitted (deny-by-default). |
| **500** | Any server-caused failure — generic apology + `request_id`, no detail. |
| **Maintenance** | Planned maintenance window / maintenance mode (`Observability.md` §2, Maintenance Center). |

Each page is workspace-/locale-aware, shows the **`request_id`** the user can
quote to support, and **MUST NOT** render any stack trace or internal detail in
production (§0, §2).

### 7.2 API error envelope

All non-2xx API responses return the single consistent envelope
(`API_GUIDELINES.md` §B7), with required fields **`code`**, **`message`**,
optional **`details`** (field-level problems for `422`), and **`request_id`**
(also returned as a response header). The envelope **MUST NOT** leak stack traces,
SQL, file paths, or secrets.

> **EXAMPLE — illustrative `422` envelope (see `API_GUIDELINES.md` §B7):**
> ```json
> { "error": { "code": "validation_failed",
>   "message": "The request could not be processed.",
>   "details": [ { "field": "email", "code": "invalid", "message": "Not a valid email." } ],
>   "request_id": "01JABCXYZ8Q7R7K3MENV9T6P2" } }
> ```

---

## 8. Correlation / Request IDs

Every inbound request is assigned a **correlation `request_id`** at the start of
the lifecycle (`ARCHITECTURE.md` §7; `Core_Kernel.md` §4) — a ULID-style,
non-guessable identifier that contains no PII and is never secret.

- It is **attached to every log record** (§6), **returned in the API error
  envelope** and as a **response header** (`API_GUIDELINES.md` §B7), and **shown on
  HTML error pages** (§7.1) — the join key across **logs, errors, and traces** so
  an operator can find the exact request from the id a user quotes (Phase 15
  **Log Explorer** / **Error Tracking** — `Observability.md` §3, §4).
- If an inbound request carries a trusted correlation id (upstream proxy or the
  Integration Platform), the handler **MAY** adopt/propagate it; otherwise it
  generates one. For background/queued work the originating `request_id` SHOULD
  propagate so async failures correlate to their trigger (`BACKGROUND_JOBS.md`,
  Phase 12).

---

## 9. Recovery & Graceful Degradation

Handling is not only about reporting — the platform **SHOULD degrade gracefully**
rather than fail hard where it safely can.

- **Optional dependencies degrade.** When an *optional* capability is unavailable
  (Search index, an AI provider, a non-critical integration), the feature
  **SHOULD** degrade — disable it, show a clear notice, queue for retry — rather
  than fail the whole request (`ARCHITECTURE.md` §5; `API_GUIDELINES.md` §A4); the
  Health Checker surfaces the degraded dependency (`HEALTH_CHECK_SYSTEM.md` §8).
- **Critical dependencies fail closed.** When a *critical* dependency fails (e.g.
  the database), the request fails with a safe **500**; the system never serves
  partial or cross-tenant data to paper over a failure — **security failures fail
  closed, never open** (`SECURITY_GUIDE.md` §1).
- **Retries for transient faults** belong in the Infrastructure/queue layer
  (idempotent, bounded, backoff — `BACKGROUND_JOBS.md`, Phase 12), not controllers;
  heavy/AI work runs async so a provider blip retries without blocking the user
  (`ARCHITECTURE.md` §8). **Maintenance mode** returns the maintenance surface
  (§7.1) during planned windows (`Observability.md` §2). Degradation is always
  **explicit and logged** — a silently ignored failure is a defect
  (`CODING_STANDARD.md` §7, §10).

---

## 10. Phase 15 Hand-off — Error Tracking

The Error Handler is the **producer** of error signals; the **Observability**
module is the **consumer** (`Observability.md` §1, §4). On capture, the handler
logs safely (§6) and emits **`kernel.exception.captured`** (`Core_Kernel.md` §7);
Phase 15 **Error Tracking** ingests these and groups them into **fingerprinted
Error Groups** with occurrence trends, correlating by exception type, normalized
message, and origin, and tying occurrences together via the **`request_id`**
(`Observability.md` §4, §8; `ERROR_TRACKING.md`). Security-relevant errors (auth
failures, permission-denied spikes, cross-tenant attempts) also feed the
**Security Center**, and threshold breaches drive the **Alert Engine**, delivered
**only** through the Integration Platform — the kernel never sends alerts itself
(`Observability.md` §2, §7). Until Phase 15 ships, the handler still fully
protects users (safe pages/envelopes) and operators (structured logs); Error
Tracking is **additive** on top of the same signals.

---

### Related Documents

`ARCHITECTURE.md` (§6) · `CODING_STANDARD.md` (§7) · `Core_Kernel.md` ·
`PROJECT_STRUCTURE.md` · `API_GUIDELINES.md` (§B4, §B7) · `SECURITY_GUIDE.md` ·
`PERMISSION_MODEL.md` · `UI_GUIDELINES.md` · `HEALTH_CHECK_SYSTEM.md` ·
`DEPLOYMENT_GUIDE.md` · `BACKGROUND_JOBS.md` (Phase 12) · `Observability.md` ·
`OBSERVABILITY.md` (Phase 15) · `ERROR_TRACKING.md` (Phase 15)
