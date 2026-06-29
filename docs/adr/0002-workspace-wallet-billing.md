# ADR 0002 — Workspace Wallet Billing (seats + features, prepaid credits)

> **Status:** Accepted · **Date:** 2026-06-29 · **Supersedes (in part):** the
> per-account/per-user subscription emphasis of `BILLING_PLATFORM.md`.

## Context

The platform billed around the **user/account** (`account_plans` caps how many
workspaces an owner runs; `subscriptions` attaches a Free/Pro/Enterprise plan per
workspace). The product direction is now explicit: **a Workspace is a company**
(Constitution §3.5; product mindset #2, #7). Billing must therefore belong to the
Workspace, not the user. A person can be a free Candidate in unlimited workspaces;
only **running a company** (the Owner adding staff and enabling capabilities) is
paid for.

Requirements gathered from the product owner:

- Each Workspace has **its own wallet and payment**, independent of other
  workspaces the same user owns.
- The **Owner is free**; every additional staff member (any non-candidate with
  permissions) is a **billable seat**.
- **Basics** (Jobs, Interviews) are free; other **features are flat-priced** and
  added to the plan when enabled.
- Owner **prepays credits** (free amount) via **Fawaterak**, then **composes** a
  plan; cost is debited from the wallet. Plan term is **1 month from payment**.
- **Add-ons** after activation are charged now and **expire with the plan**.
- **Auto-renew from the wallet**; on insufficient funds the workspace is
  **locked** (staff blocked except the billing page for `billing.manage` holders;
  mid-month seat additions cost a **full month**).
- Prices are set by the **platform** (`system.pricing.manage`), not the Owner.
- **Platform does not pay provider (AI) costs** — customers bring their own keys;
  only Platform API is metered by us (Constitution §8, mindset #8).

## Decision

Add a **prepaid wallet** and a **composed monthly plan** per workspace, alongside
the existing billing tables (do not rewrite — Constitution mindset #12):

- New tables: `wallets`, `wallet_transactions` (append-only ledger),
  `pricing_catalog`, `billing_features`, `workspace_plans`,
  `workspace_plan_features`, `workspace_plan_addons`, `fawaterak_payments`.
- New services in the **Billing** module: `WalletService`, `PricingCatalog`,
  `FeatureCatalog`, `SeatCounter`, `PlanComposer`, `AddonService`, and a
  `FawaterakGateway` behind the `PaymentGateway` / new `HostedCheckoutGateway`
  contract.
- Extend `Entitlements` so a workspace with a composed plan derives its features
  from `workspace_plan_features` (+ live add-ons); workspaces without a composed
  plan keep the legacy/permissive behaviour.
- Extend `SubscriptionLifecycle::tick()` to auto-renew composed plans from the
  wallet and set `locked` on failure.
- Enforce the `locked` gate in `WorkspaceShell` (the staff page chokepoint),
  reusing the existing "service paused" pattern; candidates/public pages are
  exempt.
- Publish billing domain events for the Workflow product.

## Alternatives considered

- **Reuse `subscriptions` for the composed plan.** Rejected: its
  trial/past_due/grace state machine and the `AccountPlan`/`WorkspaceAllowance`
  coupling would entangle the new wallet semantics. A dedicated `workspace_plans`
  table keeps the new model clean and the legacy path untouched.
- **Charge Fawaterak directly per plan (no wallet).** Rejected: the product
  requires prepaid **credits** with free top-up amounts and instant, repeated
  internal charges (seats, add-ons, renewals) that must not each bounce to a hosted
  checkout.
- **Pro-rata mid-month seats.** Rejected by the product owner — a **full month**
  per seat, expiring with the plan.

## Consequences

- Money enters **only** via Fawaterak top-up (one audited contract surface);
  everything else is an internal wallet debit with a full ledger.
- Backward compatible: existing workspaces with no composed plan behave exactly as
  before until their Owner composes a plan.
- Documentation (`BILLING_PLATFORM.md`, `WALLET_AND_BILLING.md`,
  `PERMISSION_CATALOG.md`, `DATABASE_ARCHITECTURE.md`, `WORKFLOW_EVENTS.md`) is
  updated in the same change set (Constitution §12, mindset #11).
