# FEATURE SPEC — Billing

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Billing · **Layer:** Commerce · **Implemented in:** Phase 14
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Billing module executes the **financial mechanics** of HaHireAI: it processes
**payments**, issues **invoices**, applies **coupons**, records payment methods,
and reconciles **payment-provider** activity. It turns a workspace's commercial
intent — owned as state by **Subscriptions** — into actual money movement and a
durable financial record.

Billing supports multiple payment providers (**Stripe**, **Moyasar**, and others),
but it reaches **every provider exclusively through the Integration Platform**
(`MODULES.md` §5). Billing never opens an outbound connection or holds provider
transport itself; it requests payment operations and consumes provider webhooks
that the Integration Platform receives, verifies, and dispatches. All financial
data is **tenant-isolated**: invoices, charges, coupons, and payment methods are
per workspace and never cross workspaces (`SECURITY_GUIDE.md` §4.2).

## 2. Scope

**In scope**

- **Payment processing** — initiate and confirm charges for subscription
  renewals, plan changes, and one-off items, via provider operations brokered by
  the Integration Platform.
- **Invoices** — generate, number, store, and expose invoices and receipts per
  workspace; track paid/unpaid/refunded states.
- **Coupons / discounts** — define and apply coupons to invoices/charges
  (validity, redemption limits, scope).
- **Payment methods** — store provider-tokenized payment-method references
  (never raw card data) per workspace.
- **Provider webhook handling** — consume verified payment events
  (succeeded/failed/refunded/chargeback) dispatched by the Integration Platform
  and update financial records accordingly.
- **Payment flow + webhook verification** — the end-to-end charge → confirm →
  reconcile flow, where verification of inbound provider callbacks is performed by
  the Integration Platform's signature/replay controls before Billing acts.
- **Refunds** — process refunds against prior charges where permitted.

**Out of scope**

- **Subscription status/lifecycle** — owned by **Subscriptions**; Billing reports
  outcomes and Subscriptions transitions state.
- **Feature flags, limits, entitlements** — owned by **Licensing**.
- **Direct provider connectivity** — all Stripe/Moyasar/other transport and
  inbound-webhook verification go through the **Integration Platform**.
- **Plan catalog authoring** — global plans live in the Platform Context.
- **Tax/accounting ledgers beyond invoices** — out of scope for Phase 14 unless a
  later spec adds them.

## 3. Inputs

- **Charge requests** — from Subscriptions (renewal, plan change) or from internal
  use cases for one-off items, carrying amount (minor units + currency),
  workspace, and an optional coupon.
- **Coupon application** — a coupon code applied to an invoice/charge.
- **Payment-method setup** — a request to attach a provider-tokenized payment
  method to the workspace.
- **Provider webhooks** — verified, de-duplicated payment events delivered by the
  Integration Platform (payment succeeded/failed/refunded/disputed).
- **Refund requests** — a permitted member or System Owner refunds a prior charge.
- **System Owner actions** — Platform Context billing oversight via
  `system.subscriptions.manage` (read/adjust financial records across tenants).

## 4. Outputs

- **Payment operations** — charge/refund/setup requests handed to the Integration
  Platform's payment connector contract (Billing never calls the provider).
- **Invoices & receipts** — generated documents and their states, exposed per
  workspace and downloadable through the tenant-guarded Files/controller path.
- **Payment outcome events** — published for Subscriptions and the platform (see
  §7), so subscription status can advance/recover.
- **Coupon redemption records** — applied discounts and remaining redemptions.
- **Audit entries** — payments, refunds, coupon applications, and payment-method
  changes are auditable.
- **Billing metrics** — emitted to Observability via shared services.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — container, configuration, Event Dispatcher, Logger.
- **Integration Platform** — the **only** path to payment providers: outbound
  charge/refund operations and inbound provider-webhook verification/dispatch.
- **Subscriptions** — receives charge/renewal requests from and returns payment
  outcomes to Subscriptions (which owns status).
- **Workspaces** — every financial record belongs to one workspace (tenancy root).
- **Licensing** — consulted where a paid action depends on entitlement; Billing
  does not self-decide availability.
- **Files** — invoice/receipt documents are stored and served via the shared,
  tenant-guarded Files service.
- **Notifications** — payment-receipt and payment-failure notices are sent by
  subscribers to Billing events (Billing does not depend on Notifications).
- **Audit** — records financial actions.
- **Permissions** — every action passes an Application-boundary permission check by
  key.
- **Observability** — billing metrics/errors via shared services.

## 6. Permissions (keys this module declares)

Workspace-scoped:

- `billing.view` — view invoices, payments, coupons, and payment methods.
- `billing.manage` — manage payment methods, apply coupons, initiate/refund
  payments, and download invoices (shared with Subscriptions for the commercial
  surface).

System-scoped (Platform Context only):

- `system.subscriptions.manage` — platform-level oversight of billing/financial
  records across workspaces (Commerce is administered together in the Platform
  Context).

## 7. Events (Published / Subscribed)

**Published** (`billing.<entity>.<event>`, past tense):

- `billing.payment.succeeded`
- `billing.payment.failed`
- `billing.payment.refunded`
- `billing.invoice.issued`
- `billing.invoice.paid`
- `billing.coupon.applied`
- `billing.paymentmethod.attached`

**Subscribed:**

- `subscriptions.subscription.renewed` / `subscriptions.subscription.plan_changed`
  — trigger the corresponding charge/invoice.
- `integration.webhook.received` (payment-provider events, post-verification) —
  reconcile charges and emit payment outcomes.

Every event carries `workspace_id`; publishers assume no specific subscriber, and
subscribers degrade gracefully when a publisher is disabled.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **Invoice** *(workspace-scoped, root)* — number, line items, amounts (minor
  units + currency), status (issued/paid/refunded/void), period.
- **Payment / Charge** *(workspace-scoped)* — provider reference, amount, status,
  related invoice, idempotency key.
- **Refund** *(workspace-scoped)* — reference to a prior charge, amount, reason.
- **Coupon** *(workspace-scoped or global reference)* — code, discount,
  validity window, redemption limit.
- **Coupon Redemption** *(workspace-scoped)* — coupon applied to an invoice/charge.
- **Payment Method** *(workspace-scoped)* — provider-tokenized reference only
  (no raw card/PAN), brand, last-4, expiry hint.

All workspace-scoped records carry `workspace_id`; identifiers are ULIDs. Provider
secrets/keys are held by the Integration Platform and encrypted at rest; Billing
stores only tokenized references and never raw card data.

## 9. Acceptance Criteria (testable checklist)

- [ ] All payment-provider transport (Stripe/Moyasar/others) goes through the
  Integration Platform; Billing makes no direct external calls (verified by
  dependency review).
- [ ] Inbound provider webhooks are verified (signature + replay) by the
  Integration Platform **before** Billing acts on them.
- [ ] Charge requests honor an idempotency key so a retried request never charges
  twice.
- [ ] All financial records carry `workspace_id` and are invisible to other
  workspaces; cross-tenant negative tests pass.
- [ ] Invoices are generated, numbered, and downloadable only through a
  tenant-guarded, permission-checked path.
- [ ] Coupons enforce validity windows and redemption limits; an invalid/expired
  coupon is rejected.
- [ ] Payment methods store only provider-tokenized references; raw card data is
  never stored.
- [ ] Payment outcomes are published as events (`billing.payment.succeeded`,
  `billing.payment.failed`, …) that Subscriptions consumes to drive status.
- [ ] `billing.view` is required to view and `billing.manage` to perform billing
  actions; Platform Context oversight requires `system.subscriptions.manage`.
- [ ] Refunds reference a prior charge and are permission-gated and audited.
- [ ] Billing metrics/errors are emitted to Observability via shared services; no
  monitoring lives inside the module.

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`Subscriptions.md` · `Licensing.md` · `Integration_Platform.md` ·
`SECURITY_GUIDE.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`DATABASE_ARCHITECTURE.md`
