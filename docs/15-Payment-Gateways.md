# 15 — Payment Gateways (بوابات الدفع)

A pluggable payment-gateway layer behind a single `PaymentGatewayInterface` with a registry, Saudi-friendly providers (Moyasar, Tap, HyperPay), tokenized methods, idempotent webhooks, and PCI-conscious design — no gateway is ever hard-coded.

## Related Documents

- [14 — Billing System](14-Billing-System.md) — invoices/payments this layer settles; receipts and refunds it executes.
- [13 — Subscription System](13-Subscription-System.md) — charges keep subscriptions `active`; failures trigger `past_due`/dunning.
- [34 — Security](34-Security.md) — PCI, secret encryption (AES-256-GCM), webhook verification.
- [05 — Database Architecture](05-Database-Architecture.md) — canonical `payments`, `payment_methods`, `gateway_events` schema.
- [12 — Workspace Management](12-Workspace-Management.md) — per-tenant gateway configuration via workspace settings.

---

## Purpose (الهدف)

This layer is how HalaOps actually **moves money**: it charges a card to settle an invoice, stores a reusable **token** for renewals, processes asynchronous **webhooks** to confirm outcomes, and issues refunds. The whole capability sits behind one contract, `PaymentGatewayInterface`, with a **registry** that resolves the configured gateway at runtime. Adding a provider is a new class plus a registry entry — never an edit to billing logic or an `if ($gateway === 'stripe')`. It ships oriented to **Saudi-friendly** gateways (Moyasar, Tap, HyperPay) with Stripe and PayPal documented as future, and is designed so **no card PAN is ever stored** by HalaOps.

The schema is `payments`, `payment_methods`, `gateway_events` (canonical [§11](05-Database-Architecture.md)).

## Why It Exists (سبب وجوده)

Different markets and merchants use different processors; a Saudi customer may use Moyasar or HyperPay, another may prefer Tap, and an international tenant might want Stripe. Hard-coding any one of them would make HalaOps unsellable to the others and would scatter gateway-specific quirks (auth, tokenization, webhook signatures, refund semantics) across the codebase. A single interface + registry isolates those differences so the billing system ([14](14-Billing-System.md)) speaks one abstract language. Tokenization + "never store PAN" keeps HalaOps out of the heaviest **PCI-DSS** scope. Idempotent webhook handling via `gateway_events` makes payment confirmation reliable even when networks retry or the app crashes mid-charge.

## Architecture

| Component | Where | Responsibility |
|-----------|-------|----------------|
| `PaymentGatewayInterface` | `app/Services/Billing/Gateways/PaymentGatewayInterface.php` (planned) | The contract every gateway implements: `charge()`, `refund()`, `createToken()` / `tokenize()`, `verifyWebhook()`, `parseEvent()`, capability flags. |
| Gateway registry | `app/Services/Billing/GatewayManager` (planned) | Maps a gateway key (`moyasar`,`tap`,`hyperpay`,…) to its class; resolves the active gateway from tenant/platform config; lists available gateways. |
| Concrete gateways | `app/Services/Billing/Gateways/{Moyasar,Tap,HyperPay,Stripe,PayPal}Gateway.php` (planned) | Provider-specific HTTP, tokenization, signature verification, refund semantics. |
| `payments` | table | Records each charge/refund result (`gateway`, `gateway_reference`, `status`, `raw`). |
| `payment_methods` | table | Tokenized cards on file (`token`, `brand`, `last4`, `exp_*`, `is_default`). |
| `gateway_events` | table | Idempotent webhook log (`gateway`, `event_type`, `reference`, `payload`, `processed`). |
| Webhook controller | `app/Controllers/Billing/WebhookController` (planned) | Public endpoint `POST /webhooks/{gateway}`; verifies, dedupes, dispatches to billing. |
| `Encrypter` | `app/Core/Encrypter.php` | AES-256-GCM for gateway secret keys stored per tenant. |

This mirrors the proven **provider pattern** already used for AI (`AiProviderInterface` + `AiProviderManager`, [17 — AI Providers](17-AI-Providers.md)): an interface, a manager/registry, and swappable implementations.

```mermaid
flowchart LR
    BM[BillingManager] -->|charge/refund| GM[GatewayManager registry]
    GM -->|resolves active| GI[PaymentGatewayInterface]
    GI --> M[MoyasarGateway]
    GI --> T[TapGateway]
    GI --> H[HyperPayGateway]
    GI -. future .-> S[StripeGateway]
    GI -. future .-> P[PayPalGateway]
    WH[Webhook POST /webhooks/&#123;gateway&#125;] --> GE[(gateway_events)]
    GE --> BM
```

### The interface (conceptual signature)

```php
interface PaymentGatewayInterface
{
    public function key(): string;                                   // 'moyasar' | 'tap' | ...
    public function charge(ChargeRequest $r): ChargeResult;          // token or one-off source -> reference
    public function refund(string $reference, float $amount): RefundResult;
    public function tokenize(TokenRequest $r): PaymentToken;         // store card -> reusable token
    public function verifyWebhook(Request $r): bool;                 // signature/HMAC check
    public function parseEvent(Request $r): GatewayEvent;            // normalized event_type + reference + status
    public function supports(string $capability): bool;             // '3ds' | 'tokenization' | 'refunds' | 'recurring'
}
```

## Workflow

### Charge + webhook (settling an invoice)

```mermaid
sequenceDiagram
    autonumber
    participant U as Owner
    participant B as BillingManager (doc 14)
    participant GM as GatewayManager
    participant GW as Gateway (e.g. Moyasar)
    participant DB as Database
    participant WH as WebhookController

    U->>B: pay invoice (or cron renewal)
    B->>GM: gateway(forTenant)
    GM-->>B: PaymentGatewayInterface impl
    B->>DB: INSERT payments (status='pending')
    B->>GW: charge(token, total, currency, 3DS if required)
    alt 3-D Secure required
        GW-->>U: redirect to ACS challenge
        U-->>GW: completes 3DS
    end
    GW-->>B: synchronous ack + gateway_reference
    Note over GW,WH: Authoritative result arrives asynchronously
    GW->>WH: POST /webhooks/moyasar (signed event)
    WH->>GW: verifyWebhook(signature)
    WH->>DB: INSERT gateway_events (reference, payload, processed=0)  %% UNIQUE-guarded
    alt event not seen before
        WH->>DB: UPDATE payments SET status='succeeded', paid_at=now
        WH->>DB: UPDATE invoices SET status='paid'
        WH->>DB: keep subscription 'active'
        WH->>DB: gateway_events.processed=1
    else duplicate/replay
        WH->>WH: no-op (idempotent)
    end
    WH-->>GW: 200 OK
```

### Tokenizing a payment method

The card is collected by the gateway's hosted fields / SDK on the client and exchanged for a **token**; HalaOps receives only the token plus display metadata (`brand`, `last4`, `exp_month`, `exp_year`) and stores them in `payment_methods`. The PAN/CVV never touch HalaOps servers.

### Refund

`BillingManager` calls `gateway->refund(reference, amount)`; on success it writes a `payments` row with `status='refunded'` and updates/voids the related invoice ([14](14-Billing-System.md)).

### Per-tenant sandbox/live configuration

Each workspace stores its own gateway credentials and mode (`sandbox`/`live`) in workspace `settings` (encrypted secret keys via `Encrypter`, AES-256-GCM), mirroring how AI keys are stored per tenant ([16 — AI Architecture](16-AI-Architecture.md)). Public/publishable keys are stored plainly; secret keys are encrypted at rest. A platform-default gateway can also be configured for tenants that don't bring their own.

## Business Rules

1. **No gateway is hard-coded.** Billing depends only on `PaymentGatewayInterface`; the concrete gateway is resolved by the registry from configuration. Adding one = new class + registry entry.
2. **Saudi-friendly first.** Moyasar, Tap and HyperPay are the primary supported gateways (SAR, mada, Apple Pay where applicable); **Stripe and PayPal are documented as future** behind the same interface.
3. **Never store PAN or CVV.** Only gateway **tokens** and non-sensitive `brand`/`last4`/`exp_*` are persisted (`payment_methods`).
4. **Webhooks are idempotent.** Every event is logged in `gateway_events` keyed by `(gateway, reference)`/event id; a duplicate is a no-op. The webhook — not the synchronous response — is the authoritative source of final payment state.
5. **Webhooks are verified.** Each gateway's `verifyWebhook()` checks the provider signature/HMAC before any state change; unverified events are rejected and logged.
6. **Secret keys are encrypted at rest** (AES-256-GCM via `Encrypter`); they are per-tenant and never logged or returned to the client.
7. **3-D Secure (SCA)** is supported: when a gateway/issuer requires it, the charge flow performs the challenge redirect and resumes on return.
8. **One default method per company** (`is_default`) is used for renewals; setting a new default unsets the previous.
9. **Sandbox vs live** is explicit per tenant; sandbox transactions never hit live processors and are visibly flagged.
10. **Payments are tenant-scoped**; `gateway_events` is a global webhook log (events arrive before any tenant context is known) and is reconciled to a tenant via the stored `payment`/`reference`.
11. **Refunds and tokens** are only meaningful if the active gateway `supports()` them; capability flags are checked before offering the action.

## Database Relations

Consistent with [§11 of the canonical schema](05-Database-Architecture.md):

- **`payment_methods`** (tenant): `id`, `workspace_id → workspaces(id) ON DELETE CASCADE`, `gateway`, `token`, `brand`, `last4`, `exp_month`, `exp_year`, `is_default`, timestamps. The `token` is the gateway's reusable reference — **not** a card number.
- **`payments`** (tenant): `id`, `workspace_id → workspaces(id) ON DELETE CASCADE`, `invoice_id → invoices(id) ON DELETE SET NULL`, `gateway`, `gateway_reference`, `amount`, `currency`, `payment_status_id → payment_statuses(id) ON DELETE RESTRICT` (config-driven; `key`s `pending`/`succeeded`/`failed`/`refunded`), `paid_at`, `raw JSON`, timestamps. Index `payments(workspace_id, gateway_reference)`.
- **`gateway_events`** (GLOBAL webhook log): `id`, `gateway`, `event_type`, `reference`, `payload JSON`, `processed`, `received_at`. Index `gateway_events(gateway, reference)` — the idempotency key.
- Upstream: **`invoices`**/**`subscriptions`** ([14](14-Billing-System.md), [13](13-Subscription-System.md)). Tenant gateway credentials live in **`settings`** (encrypted).

`raw` and `payload` retain verbatim gateway data for audit/reconciliation. `gateway_events` is deliberately **not** tenant-scoped because an inbound webhook has no session/tenant; the handler maps it to a tenant through the referenced `payment`/invoice.

## Permissions

| Action | Permission | Default roles |
|--------|-----------|---------------|
| View payment methods & payment history | `billing.view` | owner, admin |
| Add/remove/set-default payment method, charge, refund, configure gateway | `billing.manage` | **owner only** |
| Configure tenant gateway keys (also touches AI-style secret storage) | `billing.manage` (+ `settings.manage` for the settings surface) | owner |
| **Webhook endpoint** `POST /webhooks/{gateway}` | **No user auth** — authenticated by gateway **signature** instead | n/a (machine-to-machine) |

Platform-level gateway defaults/diagnostics use `platform.diagnostics` (see [22 — Super Admin Journey](22-SuperAdmin-Journey.md)). All interactive routes use CSRF; the webhook route is CSRF-exempt by necessity and instead relies on signature verification.

## Validation

- **Add payment method**: a `token` from the gateway is required (never a raw PAN); `brand`, `last4` (exactly 4 digits), `exp_month` (1–12), `exp_year` (>= current year). `gateway` must be a registered key.
- **Charge**: `amount > 0`, `currency` ISO-4217 matching the invoice; a valid `payment_method.token` or a one-off source.
- **Refund**: `amount > 0` and `<= original payment amount`; original payment `status='succeeded'`; gateway must `supports('refunds')`.
- **Gateway config**: `gateway` in the registry; mode `in:sandbox,live`; secret key non-empty (encrypted before persist); webhook secret stored for verification.
- **Webhook**: signature **must** verify; `event_type` and `reference` present; payload size-limited; otherwise rejected (and logged) before any DB mutation.

## Edge Cases

1. **Duplicate / replayed webhook** — `gateway_events` unique on `(gateway, reference)`/event id makes reprocessing a no-op; payment state is never double-applied.
2. **Charge succeeds but synchronous response is lost** (timeout/crash) — the webhook reconciles: the `pending` payment is promoted to `succeeded` and the invoice to `paid` from the event.
3. **Webhook arrives before the synchronous ack** — handler upserts on `reference`; order independence is guaranteed by the idempotency key.
4. **Unverified/forged webhook** — `verifyWebhook()` fails; event is rejected with `processed=0` recorded for audit; no state change.
5. **3-D Secure abandoned** by the user — charge stays `pending`/`failed`; subscription unaffected until a successful attempt; dunning may retry ([14](14-Billing-System.md)).
6. **Gateway outage** — `charge()` failure is caught; the payment is `failed`, the invoice stays `open`, and the renewal enters dunning; the owner is notified.
7. **Tenant switches gateways** — old tokens are gateway-specific and cannot be used with a new gateway; the owner re-adds a method; historical payments retain their original `gateway`.
8. **Expired card token** — gateway rejects the charge; flagged on the `payment_methods` row and surfaced to the owner to update.
9. **Refund larger than original** — rejected at validation.
10. **Currency mismatch** between token/gateway and invoice — rejected before charging.
11. **Sandbox key used in live mode (or vice-versa)** — caught at config validation / first call; transactions are clearly mode-flagged to prevent mixing.

## Security

- **PCI-DSS minimisation**: hosted fields/SDK tokenization means cardholder data never reaches HalaOps; storing only tokens + `last4`/`brand` keeps the deployment in the lightest PCI scope (SAQ-A-style) (see [34 — Security](34-Security.md)).
- **Never store PAN/CVV** — enforced by validation (only a `token` is accepted) and by schema (no PAN column exists).
- **Secret-key encryption**: gateway secret/webhook keys stored AES-256-GCM (`Encrypter`, authenticated encryption) per tenant; never logged, never sent to the browser.
- **Webhook authenticity**: signature/HMAC verification per gateway before any mutation; idempotent processing via `gateway_events`; raw payload retained for forensics.
- **Transport security**: all gateway calls over TLS; the webhook endpoint is HTTPS-only.
- **Tenant isolation**: `payments`/`payment_methods` are tenant-scoped and fail closed; the global `gateway_events` log is reconciled to a tenant only through verified references.
- **Authorization**: charging/refunding/configuring requires `billing.manage` (owner); the webhook route is machine-authenticated by signature, not by a user session.
- **Audit**: every charge, refund, method change and gateway (re)configuration is recorded in `activity_logs`; secrets are redacted in logs.

## Performance

- **Webhook hot path**: verify → upsert `gateway_events` (unique key) → update the referenced `payment`/`invoice`, all served by `gateway_events(gateway, reference)` and `payments(workspace_id, gateway_reference)`; respond `200` fast and defer heavy follow-up (receipts/emails) to the queue.
- **Charge calls are network-bound**: executed from the queued renewal worker, not the user request path, so dunning sweeps don't block web traffic.
- **Token reuse** avoids re-collecting cards and keeps renewals to a single gateway round-trip.
- **Registry resolution** is an in-memory map lookup; gateway objects are lightweight and constructed on demand.
- **Indexes** on every reference/lookup column keep reconciliation O(log n); `raw`/`payload` JSON is read only when an item is inspected.

## Testing

**Unit**
- Registry resolves `moyasar`/`tap`/`hyperpay` to the right class and throws on an unknown key.
- `verifyWebhook()` accepts a correctly signed payload and rejects a tampered one (per gateway).
- `parseEvent()` normalises each gateway's payload to a common `{event_type, reference, status}`.
- Idempotency: processing the same event twice updates state once.

**Feature (HTTP)**
- A successful charge marks the payment `succeeded` and the invoice `paid` (driven by the webhook).
- A failed charge leaves the invoice `open` and moves the subscription to `past_due`.
- `POST /webhooks/{gateway}` with a valid signature updates state; with an invalid signature returns an error and changes nothing.
- Adding a payment method stores only token + `last4`/`brand`; no PAN is ever persisted.

**Security**
- Forged/replayed webhooks cause no state change / no double-apply.
- Secret keys are encrypted at rest and absent from logs and HTTP responses.
- A non-owner cannot charge, refund, or change gateway config (403).
- Sandbox and live transactions never cross.

## Future Expansion

- **Stripe & PayPal** implementations behind the same interface (international expansion) — no billing changes required.
- **Apple Pay / mada / STC Pay** surfaced through the Saudi gateways' capability flags.
- **Saved-method management UI** and automatic card-expiry reminders (account updater where supported).
- **Multi-gateway routing/failover**: try a secondary gateway when the primary is down, selected by the registry and `supports()` flags.
- **Webhook event bus**: fan `gateway_events` into the notification/audit systems for richer reconciliation dashboards ([26](26-Notification-System.md), [38 — Audit System](38-Audit-System.md)).
- **PSP-level recurring/mandates**: delegate scheduling to gateways that support native subscriptions, while HalaOps remains the source of truth for plan/limits.

## Open Questions

None at this time. Which Saudi gateway is the shipped default and whether tenants must bring their own keys vs use a platform account are deployment/business choices; the registry + per-tenant encrypted config supports both, and Stripe/PayPal are tracked as future work above.
