# INTEGRATION PLATFORM — HaHireAI

> **Status:** Implemented (Phase 13, core) · **Version:** 1.0.0 · **Last updated:** 2026-06-28
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `WORKFLOW_ENGINE.md`, `API_GUIDELINES.md`.

---

## 1. Purpose & Scope

The **Integration Platform** is how HaHireAI talks to the outside world, in both
directions:

- **Inbound** — a token-authenticated **API Gateway** (`/api/v1`) that exposes
  workspace data and actions to external systems under the *same* RBAC as the UI.
- **Outbound** — **webhooks** that deliver domain events to subscriber URLs,
  driven by the event bus (the same backbone the Workflow Engine uses).

This document specifies the implemented core: API tokens, the gateway, rate
limiting, and signed outbound webhooks, plus the Developer Portal. OAuth/SSO and
turnkey third-party connectors are **designed here but deferred** (§7) — they
require external identity/providers that the platform integrates with per
deployment.

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119.

---

## 2. API Tokens

External callers authenticate with a **workspace-scoped API token** (a Bearer
credential), never a password or session.

- A token is minted as `hh_` + 40 hex chars and shown **exactly once** at issue
  time. Only its **SHA-256 hash** is stored; the plaintext is never persisted
  (mirrors password handling — `SECURITY_GUIDE.md`).
- A token carries a `workspace_id` and a `user_id`: it **acts as that member**.
  Its effective permissions are that membership's permissions (§3).
- Tokens may be **revoked** (`revoked_at`) and may **expire** (`expires_at`);
  neither a revoked nor an expired token ever authenticates.
- The listing shows only a **hint** (`hh_3f9a…a1b2`) plus `last_used_at`.

```
  issue ──▶ plaintext shown once ──▶ client stores it
        └─▶ sha256(plaintext) stored in api_tokens
  request ──▶ Bearer plaintext ──▶ sha256 ──▶ row lookup (active only)
```

---

## 3. The API Gateway (`/api/v1`)

The gateway is the **single authenticated REST surface**. Every request passes
the same pipeline, in order:

```
  Request
    │  1. Authenticate   Bearer token → active api_tokens row → member
    │  2. Rate-limit     fixed window per token (§4) → 429 if exceeded
    │  3. Authorize      required permission KEY held? → 403 if not (§deny-by-default)
    │  4. Scope          all queries filtered by the token's workspace_id
    ▼
  JSON envelope
```

- **Authentication** binds the request to a `(workspace, user)` and loads that
  member's effective permissions — **identical RBAC to the UI** (the gateway is
  not a privileged backdoor). A token whose member was removed from the workspace
  stops working immediately.
- **Authorization** is deny-by-default and checks permission **keys**, never role
  names (`PERMISSION_MODEL.md`). `GET /jobs` requires `job.view`, exactly as the
  Jobs screen does.
- **Tenant scope** is mandatory: every gateway query is filtered by the token's
  `workspace_id`. A token for workspace A requesting workspace B's job receives
  **404**, never B's data (verified by `IntegrationApiTest`).

### 3.1 Envelope & errors

| Shape | Body |
|---|---|
| Success | `{ "data": …, "meta"?: { … } }` |
| Error | `{ "error": { "code": "…", "message": "…" } }` |

Status codes: `200` ok · `401 unauthorized` (no/invalid token) · `403 forbidden`
(missing permission) · `404 not_found` · `429 rate_limited`.

### 3.2 Endpoints (initial)

| Method · Path | Auth | Permission | Returns |
|---|---|---|---|
| `GET /api/v1/ping` | none | — | Liveness `{status, api}` |
| `GET /api/v1/me` | Bearer | — | Token identity + effective permissions |
| `GET /api/v1/jobs` | Bearer | `job.view` | Workspace jobs (`meta.count`) |
| `GET /api/v1/jobs/{id}` | Bearer | `job.view` | One job, or 404 |

New resources are added behind the same pipeline — endpoints never re-implement
auth, limiting, scoping, or the envelope.

---

## 4. Rate Limiting

The gateway applies a **fixed-window** limit per token, shared across web
processes via the `rate_limits` table (one row per `key:window`, incremented with
`INSERT … ON DUPLICATE KEY UPDATE`).

- Default: `api_rate_limit` requests per `api_rate_window` seconds
  (config `config/integration.php`, default **120 / 60s**).
- Successful responses carry `X-RateLimit-Limit` and `X-RateLimit-Remaining`.
- On exceed: **429** with `Retry-After` and `X-RateLimit-Remaining: 0`.
- Old windows are purged by `RateLimiter::purgeOlderThan()` (ops/cron).

---

## 5. Outbound Webhooks

Webhooks deliver **domain events** to external URLs. The `WebhookDispatcher` is a
**reactor on the event bus** — exactly like the Workflow Engine. Actor modules
publish events; they never know webhooks exist (`ARCHITECTURE.md` §4,
`WORKFLOW_ENGINE.md` §2).

```
  Recruitment ──"application.submitted"──▶ EventDispatcher
                                               ├─▶ Workflow Engine  (Phase 12)
                                               └─▶ Webhook Dispatcher (Phase 13)
                                                      │ for each enabled endpoint
                                                      ▼ subscribed to the event
                                               signed HTTPS POST + delivery record
```

- An **endpoint** (`webhook_endpoints`) has a URL, a server-generated **signing
  secret**, a list of subscribed events, and an enable flag — all workspace data.
- Each delivery POSTs a JSON body `{ event, workspace_id, delivered_at, data }`
  with headers:
  - `X-HaHireAI-Signature: sha256=<HMAC-SHA256(body, secret)>` — the receiver
    recomputes this to verify authenticity and integrity.
  - `X-HaHireAI-Event`, `X-HaHireAI-Delivery`.
- Every attempt is recorded in `webhook_deliveries` (status, HTTP code, error,
  signature). Success clears the endpoint's `failure_count`; failure increments
  it. Delivery is **synchronous now, queue-ready** (the single seam for async
  retry/backoff later).
- Transport is abstracted behind an `HttpClient` contract (`CurlHttpClient` in
  production; a fake in tests) so delivery is verifiable **without real network
  calls**.

### 5.1 Tenant isolation

Endpoints, secrets, and deliveries are workspace-scoped. An event fired in
workspace B never reaches workspace A's endpoints (verified by `WebhookTest`).

---

## 6. Developer Portal

A permission-gated UI (`/integrations`, sidebar **Developer**) to:

- issue and revoke **API tokens** (plaintext revealed once);
- create, enable/disable, and delete **webhook endpoints** (secret revealed once);
- review **recent deliveries** (event, HTTP status, outcome).

Permissions: `integration.view` (see the portal), `api.tokens.manage`,
`webhook.manage`. All management actions are CSRF-protected and audited
(`integration.api_token.*`, `integration.webhook.*`).

---

## 7. The Event Bus, and What's Deferred

- **Event bus.** The in-process `EventDispatcher` is the integration backbone:
  modules publish, reactors (Workflow Engine, Webhook Dispatcher) subscribe. The
  catalog of publishable events grows additively; both reactors pick up new
  events without actor changes. Async/queued delivery is the production extension
  of this same contract.
- **Deferred (designed, not built in this phase):**
  - **OAuth2 / SSO** inbound (authorization-code clients) and **SAML/OIDC**
    workspace login — require an external IdP per deployment.
  - **Turnkey connectors** (calendars, job boards, Slack, ATS imports) — each is
    a provider adapter behind the gateway/webhook primitives defined here.
  - **Inbound webhooks** and a published **OpenAPI** spec + developer docs site.

  These build directly on the primitives in this document (tokens, gateway
  pipeline, event bus, signed delivery); they add adapters, not new
  architecture.

---

## 8. Acceptance (Phase 13)

Verified against a live MySQL 8 database, plus an end-to-end HTTP run against the
running server:

- ✅ Tokens authenticate; revoked/expired tokens do not; only a SHA-256 hash is
  stored.
- ✅ The gateway authenticates, rate-limits (headers + 429), authorizes by
  permission key, and scopes every query to the token's workspace.
- ✅ A member lacking a permission gets **403**; a cross-workspace resource gets
  **404**.
- ✅ Webhooks deliver to subscribed, enabled endpoints with a **valid HMAC
  signature**; failures are recorded and increment `failure_count`; deliveries
  are isolated per workspace.
- ✅ The `IntegrationModule` boot listener delivers a webhook when an event is
  dispatched through the **real** event bus + container.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `WORKFLOW_ENGINE.md` ·
`AI_ENGINE.md` · `API_GUIDELINES.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` · `ENTITY_CATALOG.md`
