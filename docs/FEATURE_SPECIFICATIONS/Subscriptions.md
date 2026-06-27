# FEATURE SPEC — Subscriptions

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Subscriptions · **Layer:** Commerce · **Implemented in:** Phase 14
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Subscriptions module owns the **per-workspace commercial lifecycle**: which
plan a workspace is on, what subscription **status** it currently holds, and the
transitions between those statuses (trial, conversion, renewal, dunning, grace,
cancellation, expiry). It is the single source of truth for the question *"is this
workspace's subscription active, and what plan does it reference?"*

Subscriptions is the **authoritative state machine**; it is **not** the entitlement
engine and **not** the payment engine. **Licensing** translates a workspace's plan
and status into concrete feature flags, limits, and usage entitlements; **Billing**
executes charges, invoices, and provider transactions. Subscriptions coordinates
the lifecycle and publishes the events those modules and the rest of the platform
react to. Per the Constitution, **no module decides its own feature availability** —
it asks Licensing, whose answer derives from the subscription this module owns
(`MODULES.md` §5).

## 2. Scope

**In scope**

- The **subscription lifecycle and statuses** per `STATE_DIAGRAMS.md` §9:
  **Trialing → Active → PastDue → Suspended → Cancelled / Expired**, with the
  recovery edges (pay → Active) and trial-end (no plan → Expired).
- **Plans by reference.** A subscription references a globally defined **Plan**
  (tier = features + limits); plan definitions are **never hard-coded** in this
  module's logic.
- **Trial** management (start, duration, conversion, expiry-with-no-plan).
- **Grace period** after a failed payment (PastDue window) before Suspension.
- **Renewal** scheduling (period end → renew → Active) and plan **changes**
  (upgrade/downgrade/proration request — execution delegated to Billing).
- **Cancellation** (immediate or at period end) and reactivation where allowed.
- Reacting to Billing payment outcomes to drive status transitions.
- Publishing lifecycle events the platform subscribes to.

**Out of scope**

- **Payment execution, invoices, coupons, refunds** — owned by **Billing**.
- **Feature flags, tenant limits, usage entitlements** — owned by **Licensing**;
  this module only owns the plan reference and status that Licensing reads.
- **Plan catalog authoring** — global Plan definitions are managed in the Platform
  Context (`system.subscriptions.manage`); this module consumes them.
- **Payment-provider connectivity** — reached only through the Integration
  Platform; this module never calls Stripe/Moyasar directly.
- **Workspace lifecycle** (archive/soft-delete) — owned by Workspaces; expiry and
  cancellation here **never** hard-delete business data (`STATE_DIAGRAMS.md` §9).

## 3. Inputs

- **Workspace creation / signup** — request to start a Trialing subscription (or a
  selected plan) for a new workspace.
- **Plan selection / change** — a member with billing rights chooses or changes the
  referenced plan.
- **Billing outcomes** — payment-succeeded / payment-failed signals from Billing
  that advance or recover the status.
- **Cancellation request** — immediate or end-of-period.
- **Scheduler ticks** — period-end, trial-end, and grace-period-end evaluations
  (driven by the Workflow Engine scheduler, not a timer inside this module).
- **System Owner actions** — Platform Context overrides (extend trial, suspend,
  reinstate) via `system.subscriptions.manage`.

## 4. Outputs

- **Current subscription state** — the authoritative status + referenced plan for a
  workspace, exposed via this module's contract for Licensing and others to read.
- **Lifecycle events** — published on each transition (see §7) for Licensing,
  Billing, Notifications, Reports, Audit, and the Integration Platform.
- **Renewal / charge requests** — handed to Billing to execute (this module asks;
  Billing performs).
- **Grace / expiry signals** — so gated actions become unavailable when a
  workspace is Suspended (Suspended workspaces can still log in, view data, and
  renew, but cannot perform gated actions — `STATE_DIAGRAMS.md` §9).
- **Audit entries** — every status change and plan change is auditable via the
  shared Audit service.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — container, configuration, Event Dispatcher, Logger.
- **Workspaces** — a subscription belongs to exactly one workspace; tenancy root.
- **Billing** — requests charges/renewals and receives payment outcomes (Billing
  depends on Subscriptions and the Integration Platform for provider transport).
- **Licensing** — reads subscription status + plan reference to compute
  entitlements; Subscriptions publishes the changes Licensing consumes.
- **Workflow Engine** — provides the **scheduler** for trial-end, period-end, and
  grace-end evaluations; long-running checks run as background jobs.
- **Notifications** — trial-ending, payment-failed, suspension, and renewal notices
  are sent by subscribing to this module's events (Subscriptions does not depend on
  Notifications).
- **Audit** — records lifecycle and plan changes.
- **Permissions** — every action passes an Application-boundary permission check by
  key.
- **Observability** — subscription metrics are emitted via shared services, not
  monitored inside the module.

## 6. Permissions (keys this module declares)

Workspace-scoped:

- `billing.view` — view the workspace's subscription, plan, and status.
- `billing.manage` — start/change/cancel the subscription and select a plan
  (shared with the Billing module; both govern the commercial surface a workspace
  member controls).

System-scoped (Platform Context only):

- `system.subscriptions.manage` — manage global plan definitions and override any
  workspace's subscription (extend trial, suspend, reinstate, change plan).

## 7. Events (Published / Subscribed)

**Published** (`subscriptions.subscription.<event>`, past tense):

- `subscriptions.subscription.trial_started`
- `subscriptions.subscription.activated`
- `subscriptions.subscription.renewed`
- `subscriptions.subscription.past_due`
- `subscriptions.subscription.suspended`
- `subscriptions.subscription.cancelled`
- `subscriptions.subscription.expired`
- `subscriptions.subscription.plan_changed`

**Subscribed:**

- `billing.payment.succeeded` — recovers PastDue/Suspended → Active, or confirms a
  renewal.
- `billing.payment.failed` — drives Active → PastDue and starts the grace window.
- `workspaces.workspace.created` — triggers an initial Trialing subscription where
  policy dictates.

Every event carries `workspace_id`; publishers do not assume any subscriber
exists, and subscribers degrade gracefully if a publisher is disabled.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **Subscription** *(workspace-scoped, root)* — referenced plan, status
  (Trialing/Active/PastDue/Suspended/Cancelled/Expired), current period start/end,
  trial end, grace end, cancel-at-period-end flag.
- **Subscription Status History** — immutable record of each transition (from, to,
  reason, actor) for audit and analytics.
- **Plan (reference only)** — the global Plan catalog is consumed here, **not
  owned**; ownership of plan definitions sits with the Platform Context.

All workspace-scoped records carry `workspace_id`; identifiers are ULIDs.
Cancellation and expiry **retain** business data (recoverable per
`ARCHIVING_POLICY.md`).

## 9. Acceptance Criteria (testable checklist)

- [ ] A subscription is per workspace and references a global Plan; no plan tier is
  hard-coded in this module's logic.
- [ ] The status machine exactly matches `STATE_DIAGRAMS.md` §9
  (Trialing/Active/PastDue/Suspended/Cancelled/Expired) and forbids any transition
  not listed there.
- [ ] A new workspace can begin **Trialing**; trial end with no plan transitions to
  **Expired**, and trial conversion transitions to **Active**.
- [ ] A failed payment moves Active → **PastDue** and opens a configurable grace
  window; grace-end transitions PastDue → **Suspended**.
- [ ] A successful payment recovers PastDue/Suspended → **Active**.
- [ ] **Suspended** workspaces can still log in, view data, and renew, but cannot
  perform gated actions.
- [ ] **Cancelled** and **Expired** never hard-delete business data.
- [ ] Every status change and plan change emits the corresponding event with
  `workspace_id` and writes an audit entry.
- [ ] Licensing can read the authoritative status + plan reference via contract;
  this module never computes feature availability itself.
- [ ] Renewals and charges are requested from Billing; this module never calls a
  payment provider directly.
- [ ] `billing.view` is required to view and `billing.manage` to change a
  subscription; Platform Context overrides require `system.subscriptions.manage`.
- [ ] Scheduled evaluations (trial/period/grace) run via the Workflow scheduler as
  background jobs, not via in-module timers.

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`STATE_DIAGRAMS.md` · `Billing.md` · `Licensing.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `SUBSCRIPTION_ENGINE.md` · `ARCHIVING_POLICY.md` ·
`DATABASE_ARCHITECTURE.md`
