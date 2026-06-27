# FEATURE SPEC — Integration Platform

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Integration Platform · **Layer:** Integration · **Implemented in:** Phase 13
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Integration Platform is HaHireAI's **single boundary to the outside world**.
It is the only module permitted to originate or terminate network connectivity on
behalf of the platform. Per the Constitution and `MODULES.md` §5, **no business
module calls an external service directly**; every outbound HTTP call, inbound
webhook, third-party connector, and identity-provider exchange flows through this
module's contracts and adapters.

It exposes the platform's capabilities to third parties and first-party clients as
a **versioned REST API** (`/api/v1`), runs the inbound/outbound **Webhook Engine**,
exposes the in-process **Event Bus** to external subscribers, brokers
**OAuth2/OIDC/SAML/SCIM** identity flows, hosts the catalog of **connectors**
(calendar, meeting, email, messaging, HR, CRM, storage, payment), and provides the
**Integration Marketplace** and **Developer Portal**. It binds the binding rules
already set in `API_GUIDELINES.md` (Part B) and `SECURITY_GUIDE.md` §9 to a
concrete implementation.

The Integration Platform **transports**; it does not own business logic. The REST
API is **REST over Application use cases** (`MODULES.md` §5) — it never bypasses
the Application layer, never reaches into another module's internals, and never
reads another module's tables.

## 2. Scope

**In scope**

- **API Gateway** — request ingress, authentication, workspace resolution, rate
  limiting, idempotency, request correlation, routing to Application use cases.
- **REST API (v1)** — JSON-only, URI-versioned, kebab-case plural resources,
  cursor/offset pagination, allow-listed filtering and sorting, standard status
  codes, consistent error envelope (per `API_GUIDELINES.md` Part B).
- **Token authentication** — Personal Access Tokens (PAT), Workspace tokens, and
  System tokens; scoped, revocable, hashed at rest, shown once on creation.
- **Webhook Engine** — outbound delivery (subscriptions, signing, retry with
  backoff, delivery logs) and inbound receipt (signature verification, replay
  protection, dispatch to consuming modules).
- **Event Bus exposure** — bridging selected internal domain events to external
  outbound webhooks and to streaming subscribers.
- **Identity federation transport** — OAuth2/OIDC (authorization code + PKCE),
  SAML 2.0, and SCIM 2.0 user/group provisioning endpoints, **ready** per the
  Authentication module's MFA/SSO-ready contracts.
- **Connector framework** — a uniform adapter architecture and registry for
  calendar / meeting / email / messaging / HR / CRM / storage / payment providers.
- **Integration Marketplace** — discoverable catalog of available connectors and
  apps, install/enable per workspace.
- **Developer Portal** — API reference, credential management, webhook
  configuration, scopes, and sandbox.

**Out of scope**

- **Business logic of any domain.** The REST API projects existing use cases; it
  does not implement hiring, billing, or AI behavior.
- **Deciding feature availability.** Whether an integration, connector, or API
  scope is permitted is **Licensing/Subscriptions'** decision; this module
  **asks**, it does not decide (`MODULES.md` §5).
- **Provider selection / prompting for AI.** AI provider transport is *requested*
  by the AI Engine through this module, but provider selection, prompts, and
  fallback remain the AI Engine's concern (`AI_ENGINE.md` §4).
- **Payment business rules.** Billing owns charges, invoices, and reconciliation;
  this module only carries the payment-provider transport and webhook receipt.
- **Identity decisions.** User creation, sessions, and password handling belong to
  Authentication/Users; this module transports federation and provisioning only.

## 3. Inputs

- **Inbound API requests** — `Authorization: Bearer <token>`, JSON bodies,
  `Idempotency-Key` on unsafe writes, `If-Match`/ETag for optimistic concurrency,
  query parameters for pagination/filter/sort/search.
- **Inbound webhooks** — raw request body, HMAC signature header, timestamp/nonce
  for replay protection, per-endpoint secret reference.
- **Outbound event stream** — internal domain events published on the Event Bus
  (each carrying `workspace_id`) that map to external webhook subscriptions.
- **Federation callbacks** — OAuth2/OIDC authorization responses, SAML assertions,
  SCIM provisioning requests.
- **Connector configuration** — per-workspace credentials, scopes, and settings
  for each enabled connector (stored encrypted).
- **Developer/admin actions** — token creation, webhook endpoint registration,
  connector install/enable, marketplace browsing.

## 4. Outputs

- **JSON API responses** — success envelopes wrapping `data` with `meta`/`links`
  for collections; non-2xx responses use the standard error envelope (`code`,
  `message`, `details`, `request_id`) and never leak stack traces, SQL, or
  secrets.
- **Outbound webhook deliveries** — signed (HMAC over raw body), timestamped
  payloads to subscriber endpoints, with delivery attempts and statuses recorded.
- **Delivery & request logs** — per-call request/response metadata (status,
  latency, attempts, signature result) emitted to Observability via shared
  services, never hand-rolled here.
- **Federation results** — verified identity assertions and SCIM provisioning
  outcomes handed to Authentication/Users/Memberships via their contracts.
- **Connector calls** — normalized adapter responses returned to the requesting
  module through this module's contracts.
- **Masked credentials** — token/secret references show only a masked hint and
  last-4 after creation; raw values are returned exactly once.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — container, router, configuration, environment, Event
  Dispatcher, Logger, Error Handler.
- **Event Bus** — subscribes to internal domain events for outbound webhook/stream
  bridging; the external bus is a *subscriber*, never a source business modules
  depend on (`MODULES.md` §5).
- **Permissions** — every API/connector action passes an Application-boundary
  permission check by key (never role name); tokens map to a `User`/workspace +
  permissions.
- **Workspaces / Memberships** — workspace resolution and tenant scoping for every
  request; SCIM provisioning targets memberships.
- **Authentication / Users** — federation (OAuth/OIDC/SAML) and SCIM transport
  hand off to these modules; sessions are never used for the API.
- **Licensing** — consulted (deny-by-default) before enabling a connector,
  granting an API scope/plan tier, or applying rate-limit tiers; this module never
  self-decides availability.
- **Subscriptions** — gated actions respect subscription status (e.g. Suspended).
- **Audit** — security-relevant events (token created/revoked, connector enabled,
  webhook secret rotated) are audited via the shared Audit service.
- **Observability** — request/delivery metrics, latency, and errors are emitted
  via shared services (`ARCHITECTURE.md` §8); no monitoring is implemented inside
  this module.
- **Consumed by:** the **AI Engine** (provider transport), **Billing** (payment
  provider transport), and any module needing external connectivity — all via this
  module's published contracts.

## 6. Permissions (keys this module declares)

Workspace-scoped (held by members via roles; gated by subscription + Licensing):

- `integration.view` — view integrations, connectors, and API/webhook config.
- `integration.manage` — install/enable/disable connectors and configure them.
- `apitoken.view` — list API tokens (masked) for the workspace.
- `apitoken.create` — issue a workspace/personal API token.
- `apitoken.revoke` — revoke an API token.
- `webhook.view` — view webhook endpoints and delivery logs.
- `webhook.manage` — create/update/delete webhook endpoints and rotate secrets.
- `webhook.replay` — re-deliver a past webhook event (replay a delivery log).
- `connector.connect` — authorize/connect a third-party connector account.
- `connector.disconnect` — revoke a connector connection.

System-scoped (Platform Context only, held by System Owners):

- `system.integrations.manage` — manage global connectors, the marketplace
  catalog, federation providers, and platform-level API/webhook policy.

## 7. Events (Published / Subscribed)

**Published** (`<module>.<entity>.<event>`, past tense):

- `integration.apitoken.created`
- `integration.apitoken.revoked`
- `integration.webhook.endpoint_registered`
- `integration.webhook.delivery_failed`
- `integration.webhook.delivery_succeeded`
- `integration.webhook.received`
- `integration.connector.connected`
- `integration.connector.disconnected`
- `integration.federation.provisioned` (SCIM user/group provisioned)

**Subscribed** (bridged to outbound webhooks/stream where a workspace subscribes):

- Selected domain events from other modules — e.g.
  `applications.application.submitted`, `offers.offer.accepted`,
  `subscriptions.subscription.activated`, `billing.invoice.paid` — consumed from
  the Event Bus and fanned out to subscribed external endpoints. The platform
  **MUST NOT** assume any subscriber exists, and every bridged event carries
  `workspace_id` so tenant isolation is preserved.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **API Token** — workspace/personal/system token; scopes, hashed secret, last-4,
  status, expiry, last-used.
- **API Scope** — named capability grant attached to a token (least-privilege).
- **Webhook Endpoint** — subscriber URL, event subscriptions, signing secret
  (encrypted), status.
- **Webhook Delivery** — per-attempt record: event, payload reference, status,
  attempt count, next-retry, signature result.
- **Inbound Webhook Receipt** — source, signature verification result,
  timestamp/nonce for replay protection, dispatch outcome.
- **Connector** — catalog definition (type: calendar/meeting/email/messaging/HR/
  CRM/storage/payment) and capabilities.
- **Connector Connection** — per-workspace authorized connection; encrypted
  credentials/tokens, scopes, status.
- **Federation Provider** — OAuth2/OIDC/SAML/SCIM provider configuration
  (workspace and/or global).
- **Marketplace Listing** — discoverable app/connector entry and install state.

All workspace-scoped entities carry `workspace_id`; identifiers are ULIDs; all
secrets and credentials are encrypted at rest and never returned in plaintext
after creation (`SECURITY_GUIDE.md` §6.4).

## 9. Acceptance Criteria (testable checklist)

- [ ] The REST API is JSON-only, URI-versioned at `/api/v1`, uses kebab-case
  plural resources, and returns standard HTTP status codes.
- [ ] Every collection endpoint is paginated; unbounded responses are impossible
  and a server-enforced maximum page size applies.
- [ ] Filtering and sorting accept only allow-listed fields per resource; arbitrary
  column access is rejected.
- [ ] Unsafe writes honor `Idempotency-Key`: a repeated key returns the original
  result; the same key with a different body returns `409 Conflict`.
- [ ] All non-2xx responses use the standard error envelope (`code`, `message`,
  `details`, `request_id`) and never leak stack traces, SQL, paths, or secrets.
- [ ] The API authenticates with **tokens only**; browser sessions are never
  accepted. Tokens are stored hashed and shown in full exactly once on creation.
- [ ] Every request resolves to a workspace and all returned data is filtered by
  `workspace_id`; out-of-scope resources return `404`, not `403`.
- [ ] Every endpoint enforces the required permission key (deny-by-default);
  denial returns `403`.
- [ ] Rate limits are enforced per credential (and per workspace where relevant);
  exceeding a limit returns `429` with `Retry-After`; limit tiers come from
  Licensing.
- [ ] Outbound webhooks are HMAC-signed over the raw body with a per-endpoint
  secret and retried with backoff; every attempt is recorded in a delivery log.
- [ ] Inbound webhooks are rejected unless the HMAC signature matches and the
  timestamp/nonce passes replay protection.
- [ ] A delivered webhook can be replayed only with `webhook.replay`; replays are
  auditable.
- [ ] OAuth2/OIDC, SAML, and SCIM transport is present and hands identity results
  to Authentication/Users/Memberships via contracts — this module never creates
  users or sessions itself.
- [ ] Enabling any connector or API scope is gated by a Licensing check; the
  module never self-decides availability.
- [ ] No business module calls an external service except through this module's
  contracts (verified by dependency review).
- [ ] All request/delivery metrics and errors are emitted to Observability via
  shared services; no monitoring code lives inside this module.
- [ ] Webhook secrets and connector credentials are encrypted at rest, rotatable,
  and never returned in plaintext after creation.

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`API_GUIDELINES.md` · `SECURITY_GUIDE.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `AI_ENGINE.md` · `Licensing.md` · `Billing.md` ·
`Observability.md` · `EVENT_BUS.md` · `DATABASE_ARCHITECTURE.md`
