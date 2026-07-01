# API GUIDELINES — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`. **Gateway impl:** Phase 13.

---

## 0. Purpose & Scope

HaHireAI exposes **two distinct API surfaces**, and this document governs both:

- **A) Internal module contracts** — the public APIs *inside* the modular
  monolith, by which one module talks to another. These exist from the
  foundation phases onward.
- **B) The external HTTP REST API** — the public, network-facing interface for
  third parties and first-party clients. It is **implemented in Phase 13** by the
  **Integration Platform** module (API Gateway, REST API, webhooks, event-bus
  exposure — see `MODULES.md`). The standards are **set here, now**, so the
  implementation has a fixed target.

**Supremacy.** This document defers to `PROJECT_CONSTITUTION.md` and
`ARCHITECTURE.md`; on any conflict, those win (Constitution §0). Coding-level
rules (typing, error classes, prepared statements) live in `CODING_STANDARD.md`.
JSON examples below are **EXAMPLES** only — illustrative, not source.

**Interpretation keywords.** **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY** per [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119).

---

# Part A — Internal Module Contracts

The modular monolith's "API" is the set of **published contracts and events**.
This is the primary, always-present API surface (`ARCHITECTURE.md` §3–§4).

## A1. Contracts Are the Public Surface

- A module's **public API is its `Contracts/` namespace plus the events it
  publishes** — and **nothing else** (Constitution §7; `ARCHITECTURE.md` §3).
- A module **MUST NOT** call another module's internal `Domain`, `Application`,
  `Infrastructure`, or `Persistence` classes, and **MUST NOT** read or write
  another module's tables (Constitution §4). Doing so is a critical defect.
- Interfaces in `Contracts/` are **PascalCase with no suffix**
  (`HaHireAI\Jobs\Contracts\JobService`); consumers type-hint the interface and
  the container resolves the implementation (`CODING_STANDARD.md` §4, §6).
- There is **one public contract namespace per module** (Constitution §7).

## A2. Three Sanctioned Channels

Per `ARCHITECTURE.md` §4, cross-module communication uses **only**:

1. **Contracts (synchronous)** — call another module's published interface when
   you need an immediate answer.
2. **Events (asynchronous)** — publish a domain event to the in-process Event
   Dispatcher; interested modules subscribe. This is the **async API** and the
   tool for avoiding cyclic dependencies (`MODULES.md` §5).
3. **Shared Services** — cross-cutting capabilities (Auth, Permissions, Files,
   Notifications, Search, AI, Logging, Cache, Validation) consumed via their
   contracts (`MODULES.md` §4).

**No RPC-style coupling, no shared tables, no reaching into internals.**

## A3. Contract Stability & Versioning

Internal contracts are an engineering promise between modules and **MUST** be
treated with the same care as a public API.

- Contracts are versioned by **Semantic Versioning** of their meaning
  (Constitution §14): a **breaking** change to a contract's signature or
  semantics is a **MAJOR** change; additive, backward-compatible methods are
  **MINOR**; clarifications are **PATCH**.
- **Backward-compatible evolution is the default.** Add new methods or new
  optional parameters rather than changing existing ones.
- **Breaking a contract** requires updating **every** consumer in the same
  change set and recording the decision as an **ADR** (Constitution §12, §16).
  A contract change that leaves a consumer broken is a defect.
- **Deprecation before removal.** A method to be removed is marked deprecated
  (docblock `@deprecated` + changelog note), kept for at least one MINOR cycle
  where practical, then removed in a MAJOR change.
- **Stable contracts ⇒ extractable modules.** Because consumers depend only on
  the contract, a hot module can later be extracted behind the same interface
  without rewriting its consumers (`ARCHITECTURE.md` §1, §9).

## A4. Events as the Async API

- Event names follow the canon: `<module>.<entity>.<event>` in **past tense**
  (Constitution §7) — e.g. `applications.application.submitted`.
- An event **payload is a contract**: it is versioned and evolved with the same
  backward-compatibility rules as A3. Add fields; do not repurpose them.
- Publishers **MUST NOT** assume any particular subscriber exists; subscribers
  **MUST** degrade gracefully if an optional publisher is disabled
  (Constitution §9; `MODULES.md` §5).
- Events carry the `workspace_id` so subscribers preserve tenant isolation (§B9).

---

# Part B — External HTTP REST API (Phase 13)

The external API is **REST over Application use cases** (`MODULES.md` §5),
exposed by the Integration Platform. It never bypasses the Application layer and
never reaches into module internals. The rules below are **binding on the Phase 13
implementation**.

## B1. Style: REST, JSON-Only

- The API is **resource-oriented REST**. **RPC-style** endpoints (verbs in the
  path, "do-everything" methods) are **forbidden**.
- **JSON only.** Requests and responses use `application/json; charset=utf-8`.
  Clients **SHOULD** send `Accept: application/json`; the API **MUST** respond
  with JSON, including for errors.
- HTTP methods carry their standard semantics: `GET` (safe, read), `POST`
  (create / non-idempotent action), `PUT` (full replace), `PATCH` (partial
  update), `DELETE` (remove). `GET` **MUST NOT** mutate state.

## B2. Versioning

- The API is versioned in the **URI**: `/api/v1/...`. The major version changes
  only on a **breaking** change (SemVer; Constitution §14).
- Backward-compatible additions (new endpoints, new optional fields) **MUST NOT**
  bump the version. Clients **MUST** tolerate unknown response fields.
- Removal or breaking change of a published endpoint follows a documented
  **deprecation** window (sunset header + changelog + ADR).

## B3. Resource Naming

- Resource paths use **kebab-case, plural nouns**: `/api/v1/job-postings`,
  `/api/v1/candidates`, `/api/v1/applications` (Constitution §7 route rule).
- Nest only to express ownership, and keep nesting shallow:
  `/api/v1/jobs/{jobId}/applications`. Beyond one level, prefer filtering on the
  top-level collection.
- Path identifiers are **ULIDs** (Constitution §7; `DATABASE_ARCHITECTURE.md`).
- A single resource: `/api/v1/candidates/{candidateId}`. Sub-resources and
  relationships are themselves named resources, never verbs.

## B4. Status Codes

Use **standard HTTP status codes**; do not invent meanings.

| Code | Use |
|---|---|
| `200 OK` | Successful read or update. |
| `201 Created` | Resource created (include `Location`). |
| `202 Accepted` | Async work queued (heavy/AI work — Constitution §11). |
| `204 No Content` | Successful delete / empty-body success. |
| `400 Bad Request` | Malformed syntax / invalid params. |
| `401 Unauthorized` | Missing or invalid credentials. |
| `403 Forbidden` | Authenticated but permission denied (deny-by-default). |
| `404 Not Found` | Resource absent **or** outside the caller's workspace (§B9). |
| `409 Conflict` | State conflict (e.g. idempotency/version clash). |
| `422 Unprocessable Entity` | Validation failed on a well-formed request. |
| `429 Too Many Requests` | Rate limit exceeded (§B8). |
| `500 Internal Server Error` | Unexpected failure — **never** leaks internals (§B7). |

## B5. Collections: Pagination, Filtering, Sorting, Search

Every collection endpoint **MUST** be paginated — unbounded responses are
forbidden (Constitution §11).

- **Pagination.** **Cursor-based** pagination is the **default and preferred**
  style for large/append-heavy collections; **offset/limit** **MAY** be offered
  for bounded, page-numbered views. Responses include the page items plus a
  `meta`/`links` block with the next cursor (or total/limit for offset).
  A server-enforced maximum page size **MUST** apply.
- **Filtering.** Via query parameters, e.g. `?status=screening&job-id={ulid}`.
  Allowed filter fields are an explicit allow-list per resource (no arbitrary
  column access — `CODING_STANDARD.md` §8).
- **Sorting.** `?sort=-created_at,last_name` (leading `-` = descending) over an
  allow-listed set of sortable fields, with a stable default sort.
- **Search.** Free-text search via `?q=...`, served by the shared **Search**
  service (`MODULES.md` §4), always workspace-scoped.

## B6. Writes: Idempotency & Bulk Operations

- **Idempotency keys.** Unsafe, non-idempotent writes (`POST` creates, queued
  actions) **MUST** honor an `Idempotency-Key` request header. A repeated key
  within its retention window returns the original result instead of acting
  twice; a key reused with a *different* body returns `409 Conflict`.
- **Concurrency.** Updates **SHOULD** support optimistic concurrency
  (`If-Match`/ETag or a version field); a stale update returns `409 Conflict`.
- **Bulk operations.** Where offered, a bulk endpoint accepts a bounded array
  and returns a **per-item result** with individual statuses (a partial-success
  shape), not an all-or-nothing opaque error. Bulk size is capped and the cap is
  documented.

## B7. Error Envelope

All non-2xx responses **MUST** return a single, consistent JSON envelope. Error
responses **MUST NOT** leak stack traces, SQL, file paths, or secrets — the
global Error Handler renders safe output in production (`CODING_STANDARD.md` §7;
`ARCHITECTURE.md` §6).

Required fields:

- `code` — stable, machine-readable error code (e.g. `validation_failed`).
- `message` — human-readable, safe summary.
- `details` — optional array of field-level problems (for `422`).
- `request_id` — correlation id, also returned as a response header, for support
  and log correlation (`OBSERVABILITY.md`).

> **EXAMPLE — error response, `422` (illustrative only):**
> ```json
> {
>   "error": {
>     "code": "validation_failed",
>     "message": "The request could not be processed.",
>     "details": [
>       { "field": "email", "code": "invalid", "message": "Not a valid email." }
>     ],
>     "request_id": "01JABCXYZ8Q7R7K3MENV9T6P2"
>   }
> }
> ```

## B8. Rate Limiting

- The API **MUST** enforce per-credential (and where relevant per-workspace)
  **rate limits** to protect tenants and the platform.
- Exceeding a limit returns **`429 Too Many Requests`** with a `Retry-After`
  header. Responses **SHOULD** include `RateLimit-Limit`, `RateLimit-Remaining`,
  and `RateLimit-Reset` headers.
- Limits are configured per plan/entitlement via **Licensing** (`MODULES.md`).

## B9. Authentication & Workspace Scoping

- **Token-based only. Sessions are NEVER used for the API.** Browser session
  cookies are for the server-rendered app; the REST API authenticates with
  **tokens** (Constitution §10 distinguishes the two surfaces;
  `SECURITY_GUIDE.md`).
- Credentials are presented as a **Bearer** token in the `Authorization` header.
  Supported token kinds:
  - **Personal Access Tokens (PAT)** — act as a specific `User`.
  - **Workspace tokens** — scoped to one workspace for integrations.
  - **System tokens** — for `System Owner` / platform-level operations
    (`system.*` permissions only; `PERMISSION_MODEL.md` §6).
- **Authorization is permission-based, deny-by-default.** Every endpoint checks
  the required **permission key** (never a role name) before acting; failure
  returns `403` (`PERMISSION_MODEL.md` §1, §5).
- **Workspace isolation is absolute.** Every request resolves to a workspace, and
  **all** returned data is filtered by `workspace_id`. A workspace can never read
  another workspace's data; out-of-scope resources return `404`, not `403`, to
  avoid leaking existence (Constitution §5; `ARCHITECTURE.md` §8;
  `WORKSPACE_MODEL.md`).
- All API access **MUST** be over **HTTPS** (Constitution §10). Tokens are never
  placed in URLs or logs.

## B10. Request & Response Conventions

- Timestamps are **UTC, ISO 8601** strings. Money is represented with explicit
  currency and minor units (no bare floats).
- Field naming in JSON is consistent across the API (a single documented
  convention) and stable across MINOR versions.
- Successful collection responses wrap items under a `data` key alongside a
  `meta` (and/or `links`) block for pagination.

> **EXAMPLE — request + success response (illustrative only):**
>
> Request:
> ```http
> POST /api/v1/jobs/01JAB.../applications HTTP/1.1
> Host: api.hahireai.example
> Authorization: Bearer pat_••••••••
> Idempotency-Key: 7f9c1e22-0c1a-4f0e-9b8a-2d3e4f5a6b7c
> Content-Type: application/json
>
> { "candidate_id": "01JABCD...", "source": "referral" }
> ```
>
> Response:
> ```json
> {
>   "data": {
>     "id": "01JAPPLICATIONULID000000000",
>     "job_id": "01JAB...",
>     "candidate_id": "01JABCD...",
>     "status": "submitted",
>     "workspace_id": "01JWORKSPACEULID0000000000",
>     "created_at": "2026-06-27T10:15:30Z"
>   }
> }
> ```

---

## Summary of Binding Rules

- Internal: contracts + events are the **only** cross-module API; version them
  with SemVer; never touch internals or shared tables.
- External: REST + JSON only; `/api/v1` URI versioning; kebab-case plural
  resources; standard status codes; mandatory pagination; idempotency keys on
  writes; consistent error envelope (`code`, `message`, `details`,
  `request_id`); rate limiting; **token auth, never sessions**; **all data
  workspace-scoped**; no RPC.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`PERMISSION_MODEL.md` · `CODING_STANDARD.md` · `SECURITY_GUIDE.md` ·
`WORKSPACE_MODEL.md` · `EVENT_BUS.md` · `OBSERVABILITY.md`
