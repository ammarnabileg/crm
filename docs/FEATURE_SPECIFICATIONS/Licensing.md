# FEATURE SPEC — Licensing

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Licensing · **Layer:** Commerce · **Implemented in:** Phase 14
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

Licensing is the platform's **single authority on feature availability and limits**.
It answers three questions for any workspace: *is this feature enabled?* (feature
flags), *am I within the allowed quantity?* (tenant limits), and *do I still have
budget for this metered action?* (usage entitlements). It derives those answers
from the workspace's referenced **Plan** and its current **subscription status**
(owned by **Subscriptions**).

This module exists to enforce a Golden Rule: **no module decides feature
availability on its own — it asks Licensing** (`MODULES.md` §5). A business module
**MUST NOT** branch on a plan name, count records to self-enforce a cap, or embed
plan logic; it calls Licensing's contract and respects a deny-by-default answer.
Licensing makes the safe path the default and keeps commercial policy out of
business code.

## 2. Scope

**In scope**

- **Feature flags** — per-workspace on/off availability of named features
  (e.g. AI interviews, advanced reports, SSO, webhooks), resolved from the plan
  and any per-workspace overrides.
- **Tenant limits** — quantitative caps (e.g. max members, active jobs, storage,
  API rate tier) and the check of whether an action would exceed a cap.
- **Usage entitlements** — metered allowances consumed over a period (e.g. AI
  tokens/sessions, emails, API calls), including remaining-balance checks and
  consumption recording.
- **Entitlement resolution** — combining plan defaults, per-workspace overrides,
  and subscription status into an effective entitlement set.
- **A single authorization-style contract** other modules call to ask
  "may I?" before performing a gated action.

**Out of scope**

- **Subscription status & lifecycle** — owned by **Subscriptions**; Licensing
  reads status, it does not transition it.
- **Payments, invoices, coupons** — owned by **Billing**.
- **Plan catalog authoring** — global Plan definitions are managed in the Platform
  Context (`system.subscriptions.manage`); Licensing consumes them.
- **Permission checks** — *who* may act is the Permissions module's concern;
  Licensing answers *whether the plan allows it at all*. Both checks apply: a
  gated action passes the permission check **and** the Licensing check.
- **Metering the AI itself** — the AI Engine measures token usage and cost;
  Licensing enforces the entitlement those measurements draw down.

## 3. Inputs

- **Availability queries** — a module asks "is feature X enabled for this
  workspace?" or "may I create one more of resource Y?".
- **Usage queries** — "is there remaining allowance for metered capability Z?".
- **Consumption reports** — modules (e.g. AI Engine via its usage records) report
  metered consumption to draw down an entitlement.
- **Subscription state** — current plan reference and status from Subscriptions
  (and its change events).
- **Per-workspace overrides** — System Owner adjustments to a workspace's flags or
  limits via the Platform Context.

## 4. Outputs

- **Effective entitlement set** — the resolved flags, limits, and usage allowances
  for a workspace, exposed via contract.
- **Allow/deny decisions** — a deny-by-default answer to "may I?" for a gated
  action, with a typed reason (disabled / limit-reached / quota-exhausted).
- **Remaining-balance readouts** — current usage vs. allowance for display and for
  workspace-scoped usage views.
- **Limit/usage events** — published when a limit is reached or an entitlement is
  exhausted (see §7) for Notifications and Observability.
- **Audit entries** — override changes to flags/limits are auditable.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Core Kernel** — container, configuration, Event Dispatcher, Logger, Cache
  (entitlement reads are hot and cached, then explicitly invalidated on change).
- **Subscriptions** — reads the workspace's plan reference + status and subscribes
  to its change events to refresh entitlements.
- **Workspaces** — entitlements are per workspace (tenancy root).
- **Audit** — records override changes.
- **Notifications** — limit-reached/quota-exhausted notices are sent by
  subscribers to Licensing events (Licensing does not depend on Notifications).
- **Observability** — usage and limit metrics are emitted via shared services and
  surfaced in workspace-scoped usage views.
- **Consumed by:** effectively **every** capability module — Recruitment, AI
  Engine, Integration Platform, Reports, Workflow — which call Licensing before
  performing a gated action rather than self-deciding.

## 6. Permissions (keys this module declares)

Workspace-scoped:

- `billing.view` — view the workspace's plan, enabled features, limits, and usage
  (the commercial/entitlement view shared across the Commerce surface).

System-scoped (Platform Context only):

- `system.subscriptions.manage` — manage global plan→entitlement mappings and set
  per-workspace flag/limit overrides.

> Licensing intentionally declares **no broad new workspace action permission**:
> consuming an entitlement is governed by the *calling* module's own permission key
> (e.g. `interview.ai.run`) plus the Licensing availability check. This keeps
> deny-by-default and least privilege intact without duplicating permissions.

## 7. Events (Published / Subscribed)

**Published** (`licensing.<entity>.<event>`, past tense):

- `licensing.entitlements.updated` (plan/override change recomputed entitlements)
- `licensing.limit.reached` (a tenant limit hit its cap)
- `licensing.usage.exhausted` (a metered entitlement ran out for the period)
- `licensing.feature.toggled` (a feature flag changed for a workspace)

**Subscribed:**

- `subscriptions.subscription.activated` / `.suspended` / `.expired` /
  `.plan_changed` — recompute the effective entitlement set on any commercial
  change.
- Metered-usage signals (e.g. from AI Engine usage records) — draw down the
  relevant entitlement.

Every event carries `workspace_id`; subscribers degrade gracefully if a publisher
is disabled.

## 8. Data Owned (conceptual entities only — defer to DATABASE_ARCHITECTURE.md)

- **Entitlement** *(workspace-scoped)* — the resolved value of a feature flag,
  limit, or usage allowance for a workspace.
- **Feature Flag (resolved)** *(workspace-scoped)* — enabled/disabled state of a
  named feature, derived from plan + override.
- **Limit** *(workspace-scoped)* — a named quantitative cap and its current basis.
- **Usage Counter** *(workspace-scoped)* — consumption of a metered entitlement
  within the current period.
- **Plan→Entitlement Mapping** *(global reference)* — how a Plan maps to flags,
  limits, and allowances (authored in the Platform Context; consumed here).
- **Workspace Override** *(workspace-scoped)* — System-Owner adjustment to a flag
  or limit for one workspace.

All workspace-scoped records carry `workspace_id`; identifiers are ULIDs. Plan
definitions themselves are **not owned** here — they are referenced.

## 9. Acceptance Criteria (testable checklist)

- [ ] No business module decides feature availability itself; every gated action
  calls Licensing's contract first (verified by dependency review).
- [ ] No code branches on a plan name; availability derives from resolved
  entitlements only (mirrors the no-hard-coded-roles rule).
- [ ] Licensing answers availability **deny-by-default** with a typed reason
  (disabled / limit-reached / quota-exhausted).
- [ ] Feature flags, limits, and usage entitlements are all per workspace and
  carry `workspace_id`; one workspace's entitlements never affect another.
- [ ] Entitlements are recomputed when subscription status or plan changes
  (subscribes to Subscriptions events).
- [ ] A limit check prevents an action that would exceed a cap; a usage check
  prevents a metered action with no remaining allowance.
- [ ] Metered consumption reported by callers (e.g. AI usage) draws down the
  matching entitlement accurately within the period.
- [ ] Reaching a limit or exhausting an entitlement publishes the corresponding
  event for Notifications/Observability.
- [ ] Plan→entitlement mappings and per-workspace overrides are managed only in
  the Platform Context (`system.subscriptions.manage`) and are audited.
- [ ] Entitlement reads use the cache and are explicitly invalidated on change so
  hot checks stay within performance budgets.
- [ ] Workspace-scoped usage views show remaining allowance vs. limit for
  permitted members (`billing.view`).

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`Subscriptions.md` · `Billing.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`AI_ENGINE.md` · `Observability.md` · `DATABASE_ARCHITECTURE.md`
