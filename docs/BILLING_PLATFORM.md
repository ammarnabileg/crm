# BILLING PLATFORM — HaHireAI

> **Status:** Implemented (Phase 14, core) · **Version:** 1.1.0 · **Last updated:** 2026-06-29
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `WORKSPACE_MODEL.md`, `PERMISSION_MODEL.md`.

> **v2 — Workspace Wallet Billing.** The billing unit is the **Workspace (a
> company)**. Owners prepay **credits** (Fawaterak top-up) and **compose** a
> monthly plan of **billable seats + flat-priced features**; it **auto-renews from
> the wallet** and **locks** the workspace when unfunded. This document describes
> the original plan/subscription core (still present for backward compatibility);
> the wallet model that supersedes it for new workspaces is specified in
> **`WALLET_AND_BILLING.md`** (ADR `adr/0002-workspace-wallet-billing.md`).

---

## 1. Purpose & Scope

The **Billing Platform** turns HaHireAI into a SaaS: plans, per-workspace
subscriptions, trials, invoicing, dunning (grace → suspension), and **licensing**
(plan-driven feature flags + limits). Money moves only through a
`PaymentGateway` contract — Stripe/Moyasar are adapters, never called directly,
exactly as AI providers sit behind the AI Engine.

This document specifies the implemented core. Real PSP adapters, proration, tax,
and the platform-owner subscriptions console are **designed and deferred** (§8).

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119.

---

## 2. Plans Are Data

Plans live in the `plans` table and are seeded from `PlanCatalog` — nothing in
code branches on a plan code. A plan carries price/interval/trial plus two JSON
fields that *are* the license:

- `features` — capability flags, e.g. `["ai","automation","integrations","sso"]`.
- `limits` — numeric caps, e.g. `{"members":25,"jobs":50,"api_tokens":10}`; **-1
  means unlimited**.

Default catalog: **Free** ($0, no premium features, small limits), **Pro**
($49/mo, 14-day trial, ai+automation+integrations), **Enterprise** ($199/mo,
everything + sso, unlimited). Plans are seeded on install (via the
`platform.installed` event) and by `php bin/console.php db:seed`.

---

## 3. Subscriptions & Lifecycle

Each workspace has exactly one `subscriptions` row, mutated across a state
machine. Time-based transitions run in `SubscriptionLifecycle::tick($now)` —
driven by a scheduled job, with `$now` injectable so it is fully testable.

```
  subscribe(trial plan) ─▶ trialing ──(trial ends)──▶ active        (charge ok)
                                          └──────────▶ past_due      (charge fails)
  active ──(period ends)──▶ active(renewed) | past_due
  active ──(period ends, cancel_at_period_end)──▶ canceled
  past_due ──(grace ends, default 7d)──▶ suspended
  any ──(cancel now)──▶ canceled
```

- **Trials** start only when the plan offers one and the workspace never
  subscribed before; no charge is taken during a trial.
- **Charging** happens at activation and renewal via the gateway. Success issues
  a **paid invoice** and opens a new period; failure moves to `past_due` with a
  `grace_ends_at` deadline.
- **Cancellation** defaults to *end of period* (`cancel_at_period_end`); the tick
  finalizes it. Immediate cancellation is also supported.

---

## 4. Payment Gateway Abstraction

```
  BillingService ─▶ PaymentGateway (contract) ─┬─ ManualPaymentGateway (built-in, offline)
                                               ├─ StripeGateway   (adapter, deferred)
                                               └─ MoyasarGateway  (adapter, deferred)
```

- The platform charges through `PaymentGateway::charge()` and **never** touches a
  PSP SDK. Implementations MUST NOT throw on a decline — they return an
  unsuccessful `PaymentResult`.
- The built-in **`ManualPaymentGateway`** settles offline (always succeeds with a
  traceable reference), so the platform is fully functional with no external PSP —
  the billing analogue of the AI `EchoProvider`. A real adapter is enabled by
  binding `PaymentGateway` to it.

---

## 5. Invoices

`InvoiceService` issues immutable `invoices` (unique number, amount, currency,
period, line items, status `draft|open|paid|void`). A successful charge issues an
invoice and marks it **paid** with the gateway reference. Invoices are
workspace-scoped and listed in the Billing UI.

---

## 6. Licensing — Entitlements & Feature Flags

`Entitlements` answers, from the workspace's active plan:

| Method | Answer |
|---|---|
| `allows(ws, feature)` | Is a feature flag enabled? |
| `limit(ws, key)` | The numeric cap (-1 = unlimited). |
| `within(ws, key, count)` | Would one more stay within the cap? |
| `isUsable(ws)` | False when `suspended`/`canceled`. |
| `gateFeatures(ws)` | Features for sidebar gating, or **null = do not gate**. |

**Permissive by default:** a workspace with no subscription is un-gated (features
allowed, limits unlimited) so pre-billing tenants keep working; gating becomes
real once a workspace subscribes.

### 6.1 The single dynamic sidebar, now subscription-aware

The sidebar is built by **one** `SidebarBuilder` from *(context, held permission
keys, enabled features)* — there is no per-role sidebar. An item appears only if
its **permission key is held** AND, when feature-gated, its **plan feature is
enabled**. To keep modules decoupled, the builder consumes the Core
`EntitlementResolver` contract (Billing binds the real resolver; Core binds a
permissive null default), so the Navigation/Workspaces modules never depend on
Billing internals (`ARCHITECTURE.md` §4).

---

## 7. Billing UI & Permissions

- **Workspace Billing** (`/billing`, sidebar **Billing**): current plan + status,
  trial/renewal dates, plan selection/switching, scheduled cancellation, and
  invoices. Management actions are CSRF-protected and audited
  (`billing.subscription.*`).
- Permissions: `billing.view`, `billing.manage` (keys, not roles).

---

## 8. Deferred (designed)

- Real **Stripe / Moyasar** adapters (+ webhooks → `subscription.*` events on the
  bus), **proration**, **tax/VAT**, multiple currencies per region.
- **Platform-owner** subscriptions console (`system.subscriptions.manage`,
  `/subscriptions`): all-tenant view, manual overrides, refunds.
- **Usage-based** metering (e.g. `ai_runs_month`) and hard enforcement at action
  sites via `Entitlements::within()`.

These build on the primitives here (gateway contract, plans-as-data,
entitlements, the event bus) — adapters and screens, not new architecture.

---

## 9. Acceptance (Phase 14)

Verified against a live MySQL 8 database:

- ✅ Trial plans start `trialing` with no invoice; free plans activate with no
  charge; paid switches **charge and issue a paid invoice**.
- ✅ The lifecycle converts trials to active (charging), moves failed charges to
  `past_due` then **`suspended`** after grace, and finalizes scheduled
  cancellations at period end.
- ✅ Entitlements reflect the plan (features + limits), are permissive without a
  subscription, and the **free plan gates premium sidebar items**.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `SIDEBAR_MODEL.md` · `AI_ENGINE.md` ·
`INTEGRATION_PLATFORM.md` · `ENTITY_CATALOG.md` · `SECURITY_GUIDE.md`
