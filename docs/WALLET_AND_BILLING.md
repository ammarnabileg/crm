# WALLET & WORKSPACE BILLING — HaHireAI

> **Status:** Adopted · **Version:** 1.0.0 · **Last updated:** 2026-06-29
> **Defers to:** `PROJECT_CONSTITUTION.md`, `BILLING_PLATFORM.md`.
> **ADR:** `adr/0002-workspace-wallet-billing.md`.

---

## 0. Why this document

The billing unit of HaHireAI is the **Workspace**, because **a Workspace is a
company** (Constitution §3.5; product mindset: *every Workspace is a company*).
A company owns its plan, its credits, its invoices and its billing history — **the
user owns nothing**. The same `User` may be a free **Candidate** in a million
Workspaces and pay nothing; cost accrues only to the **Owner** who *runs* a
company on the platform.

This document is the authoritative reference for the **wallet** (prepaid
credits), the **composed monthly plan** (seats + features), **add-ons**,
**auto-renewal**, the **locked** state, and the **Fawaterak** top-up flow. It
**extends** the existing Billing module; it does not replace the legacy
plan/subscription tables (`BILLING_PLATFORM.md` §2–§7), which remain for
backward compatibility.

---

## 1. The model in one paragraph

Each Workspace has a **wallet** (a prepaid balance in **USD cents**). The Owner
tops the wallet up with real money through **Fawaterak** (hosted iframe + signed
webhook). From the wallet, the Owner **composes a monthly plan**: pay **per
billable seat** (every staff member beyond the Owner) plus a **fixed price per
enabled premium feature**. The **basics** (Jobs & Interviews) are always included
at zero cost. The plan runs for **one month from the moment it is paid**, then
**auto-renews from the wallet**. If the wallet cannot cover a renewal, the
Workspace is **locked**: every staff member except those holding `billing.manage`
is denied access to all pages but the billing page, until the Owner tops up and
the plan re-activates. **Add-ons** activated mid-term are charged immediately and
**expire with the current plan**.

---

## 2. Concepts

| Concept | Meaning |
|---|---|
| **Wallet** | Per-workspace prepaid balance (USD cents). Source of truth: the ledger. |
| **Wallet transaction** | An append-only ledger row (`topup` / `charge` / `refund` / `adjustment`) carrying `balance_after_cents`. |
| **Billable seat** | Any **active staff** membership **except the Owner**. The Owner is seat #0 (free). Candidates are never seats. |
| **Basic feature** | `jobs`, `interviews` — included in every composed plan at price 0. |
| **Premium feature** | A platform capability with a fixed monthly price (e.g. `automation`, `integrations`, `ai_orchestration`, `tasks_assignment`, `platform_api`). |
| **Composed plan** | The Workspace's current month: `seats_paid` + the set of enabled features, with a price snapshot. |
| **Add-on** | A premium feature (or extra seats) activated **after** the plan started; charged now, `expires_at = plan.period_end`. |
| **Pricing catalog** | Platform-set unit prices (seat price + per-feature prices). Managed **only** by System Owners holding `system.pricing.manage`. |

> **API costs are not ours (Constitution §8 / product mindset #8).** Premium
> features that *orchestrate* a customer provider (OpenAI/HeyGen/Speech) do **not**
> meter provider cost — the customer brings their own keys. The only platform-metered
> capability is our own **Platform API access**. Feature prices are flat monthly
> charges for enabling *our* capability, never a pass-through of provider spend.

---

## 3. Pricing (platform-set)

Prices live as **data** in `pricing_catalog` and `billing_features`. Only a
System Owner with `system.pricing.manage` may edit them (Platform Context).
Workspace Owners **never set prices** — they only **compose** (choose seats +
features) and **pay** from the wallet at the platform's prices.

- `pricing_catalog` holds **scalar unit prices** — primarily `seat` (price per
  billable seat / month). It is the source of truth for the seat price.
- `billing_features` is the catalog of features shown in the composer — `key`,
  `name`, `description`, `category` (`basic|premium`), `price_cents`. A feature's
  monthly price lives here (basics are `price_cents = 0`). The `PricingCatalog`
  service unifies both: `unitPrice('seat')` and `unitPrice('feature.<key>')`.
- Currency is **USD** throughout (Fawaterak settles the top-up; the wallet and all
  internal pricing are USD cents).

---

## 4. Composing a plan (the No-Code flow)

A non-technical HR Owner does this entirely through the UI (Constitution mindset
#6 — no JSON/YAML/SQL):

1. **Top up** the wallet (free amount) via Fawaterak. Credits land after the
   signed webhook confirms payment.
2. **Compose**: pick the number of billable seats and toggle premium features.
   The composer shows a **live monthly cost** = `seats_paid × seat_price +
   Σ(enabled premium feature prices)`.
3. **Review before activation** — a mandatory screen reminds the Owner: *"Review
   whether you need extra features now; add-ons added later still expire with this
   same plan."* (so they don't buy a short-lived add-on a day later).
4. **Activate**: the composed cost is **debited from the wallet** (rejected if the
   balance is insufficient), an **invoice** is issued and marked paid, and the
   plan's `period_start = now`, `period_end = now + 1 month`.

If the wallet lacks the funds, activation is refused with a clear message to top
up first. **No money ever moves except through the wallet** (which is funded only
through the `PaymentGateway` contract → Fawaterak).

---

## 5. Seats

- `seats_paid` is what the Owner paid for this month.
- **Billable seat count** = active staff memberships − 1 (the Owner).
  Candidates are excluded entirely.
- **Adding a staff member mid-month** costs a **full month** for that seat
  (no pro-rata), debited immediately from the wallet, and the extra seat
  **expires with the current plan** (it is recorded as a seat add-on).
- Inviting/activating a staff member is **refused** when it would exceed
  `seats_paid` and the wallet cannot fund the extra seat — the UI tells the Owner
  to add a seat (top up if needed) first.

---

## 6. Add-ons

- The billing page has an **Add-ons** section listing only premium features **not
  already** in the current plan.
- Activating one is **charged immediately** from the wallet and recorded in
  `plan_addons` with `expires_at = plan.period_end`.
- Add-ons **do not extend** the plan; they end with it. On the next renewal the
  Owner chooses whether to fold them into the base plan.

---

## 7. Lifecycle & renewal

The composed plan has exactly these states:

```
 (none) ──compose+pay──▶ active ──period_end, wallet funds──▶ active (renewed)
                            │
                            └─ period_end, wallet short ─▶ locked ──topup+recompose──▶ active
```

- **`active`** — within `period_end`; all paid seats and features usable.
- **`locked`** — a renewal could not be funded. The Workspace is gated (see §8).
- Renewal is driven by `SubscriptionLifecycle::tick($now)` (the same ops/cron
  tick that already advances legacy subscriptions). On `period_end` it attempts to
  **debit the same composed cost from the wallet**; success → new month, failure →
  `locked`. **Auto-renew is the default** and on by default.
- There is no separate `grace`/`past_due` for wallet plans: funds are either there
  (renew) or not (`locked`). Locking is immediate and recoverable.

---

## 8. The locked state (gate)

When a Workspace's composed plan is `locked`:

- **Staff without `billing.manage`** are shown an **"access denied / plan paused"**
  screen on **every** workspace page. They cannot act.
- **Staff with `billing.manage`** (the Owner holds it by direct grant) may reach
  **only** the **billing area** (`/billing*`) to top up and re-activate. Every
  other page shows the same lock screen with a CTA to billing.
- **Candidates and public job pages are unaffected** — the candidate experience is
  a first-class, separate product and a company's billing state must not block
  applicants.

The gate is enforced in `WorkspaceShell` (the single chokepoint every staff page
renders through), consistent with the existing "service paused" gate. Pre-billing
Workspaces (no composed plan yet) stay **permissive/un-gated** exactly as before —
backward compatibility (Constitution mindset #12).

---

## 9. Fawaterak top-up

Top-up is the **only** place real money enters. It runs through the
`PaymentGateway` / `HostedCheckoutGateway` contracts so no business module ever
touches a provider SDK (Constitution §4, ARCHITECTURE.md §8):

1. Owner enters a **free amount** on `/billing` and submits.
2. `FawaterakGateway::createTopUpSession()` creates a Fawaterak invoice and returns
   an **iframe URL**; the Owner pays inside the embedded checkout.
3. Fawaterak calls our **paid webhook**. We **verify the signature** (provider
   HASH key), ensure **idempotency** (one credit per provider invoice), and
   `WalletService::credit()` the wallet.
4. The wallet balance updates; the Owner can now compose/renew.

Per-workspace Fawaterak credentials (providerKey, HASH/API key, OAuth client) are
stored **encrypted at rest** (`Shared\Encrypter`, AES-256-GCM) with masked hints,
exactly like AI provider keys. Failed/refunded webhooks are recorded but never
credit the wallet.

---

## 10. Data model

New tables (migrations `2026_06_27_000036`–`000039`; ULID keys, `workspace_id`
on every scoped row, tenant-guarded):

| Table | Purpose |
|---|---|
| `wallets` | One row per workspace: `balance_cents`, `currency`. |
| `wallet_transactions` | **Append-only** ledger: `type`, `amount_cents` (signed), `balance_after_cents`, `source`, `reference_id`, `description`, `actor_user_id`. |
| `pricing_catalog` | Platform unit prices: `key` (`seat` / `feature.<x>`), `unit_price_cents`. |
| `billing_features` | Feature catalog: `key`, `name`, `category`, `price_cents`. |
| `workspace_plans` | The composed monthly plan: `status`, `seats_paid`, `monthly_cost_cents`, `period_start`, `period_end`, `auto_renew`. |
| `workspace_plan_features` | Features enabled in the base plan (price snapshot). |
| `workspace_plan_addons` | Mid-term add-ons: `kind` (`feature`/`seat`), `ref`, `price_cents`, `expires_at`. |
| `fawaterak_payments` | Top-up sessions: `provider_invoice_id`, `amount_cents`, `status`, `signature_verified`. |

The legacy `plans` / `subscriptions` / `invoices` tables are unchanged; invoices
for wallet plans reuse the existing `invoices` table.

---

## 11. Permissions (keys, never roles)

| Key | Grants |
|---|---|
| `billing.view` | See the billing page, wallet balance, history. |
| `billing.manage` | Reach billing while locked; top up; compose; cancel auto-renew. |
| `billing.wallet.topup` | Start a Fawaterak top-up. |
| `billing.plan.compose` | Compose/renew the monthly plan. |
| `billing.seats.manage` | Add billable seats. |
| `billing.addons.manage` | Activate add-ons. |
| `system.pricing.manage` | **(Platform)** Edit the pricing catalog. System Owners only. |

The Workspace Owner receives all workspace billing keys by direct grant (no
reserved role). `system.pricing.manage` is a platform key.

---

## 12. Events (Workflow triggers)

Billing publishes domain events on the Event Bus so the **Workflow product** can
react (notify, create a task, email) without Billing knowing Workflow exists:

`wallet.credited` · `wallet.low_balance` · `plan.activated` · `plan.renewed` ·
`plan.lapsed` · `plan.locked` · `seat.added` · `addon.activated`.

See `WORKFLOW_EVENTS.md`.

---

## 13. The five tests (design self-check)

1. **Constitution** — workspace isolation, permissions-not-roles, platform never
   pays provider cost. ✔
2. **Modular boundaries** — Fawaterak behind a contract; cross-module via events. ✔
3. **Multi-tenant** — wallet, plan, invoices all `workspace_id`-scoped. ✔
4. **Permissions, not roles** — every gate checks a key. ✔
5. **A non-technical HR person** — top up → pick seats/features → review → activate. ✔

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `BILLING_PLATFORM.md` · `PERMISSION_CATALOG.md` ·
`DATABASE_ARCHITECTURE.md` · `WORKFLOW_EVENTS.md` · `INTEGRATION_PLATFORM.md` ·
`adr/0002-workspace-wallet-billing.md`
