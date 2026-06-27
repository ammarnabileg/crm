# 14 — Billing System (نظام الفوترة)

Invoices, payments, KSA VAT (15%), billing cycles, dunning, receipts and refunds — the money layer that turns a subscription into auditable financial records.

## Related Documents

- [13 — Subscription System](13-Subscription-System.md) — the lifecycle states that billing events drive (`active`, `past_due`, …).
- [15 — Payment Gateways](15-Payment-Gateways.md) — pluggable gateways and webhooks that actually move money and confirm payments.
- [12 — Workspace Management](12-Workspace-Management.md) — workspaces are the billed tenant; cancellation/deletion lifecycle.
- [05 — Database Architecture](05-Database-Architecture.md) — canonical `invoices`, `payments`, `payment_methods` schema.
- [07 — RBAC](07-RBAC.md) — `billing.view` / `billing.manage` gate billing operations.

---

## Purpose (الهدف)

The billing system produces and tracks the financial artifacts of a subscription: **invoices** (الفواتير) that state what is owed (subtotal, VAT, total) and **payments** (المدفوعات) that record money received against them. It defines invoice numbering, statuses, **Saudi VAT at 15%**, the billing cycle, **dunning** (التحصيل) for overdue accounts, receipts, and refunds (الاسترداد). This is the bridge between the subscription state machine ([13](13-Subscription-System.md)) and the payment gateways ([15](15-Payment-Gateways.md)).

The schema is `invoices`, `payments`, `payment_methods` (canonical [§11](05-Database-Architecture.md)); these are documented here as the authoritative billing model that the billing service implements.

## Why It Exists (سبب وجوده)

A subscription's `status` is a state flag; it is not an auditable money trail. Saudi tax law and ordinary commercial practice require a tenant to receive a proper **tax invoice** (فاتورة ضريبية) showing the 15% VAT line, a stable invoice number, and a receipt when paid. The business also needs to recover failed renewals systematically (dunning) and to issue refunds when required. Separating invoices (what is owed) from payments (what was received) — rather than a single "paid?" boolean — lets HalaOps support partial payments, multiple attempts, retries, refunds and reconciliation against any gateway, while keeping records that survive even subscription cancellation.

## Architecture

| Concern | Where | Responsibility |
|---------|-------|----------------|
| Invoice generation | Billing service (planned, e.g. `app/Services/Billing/BillingManager`) | Build invoices from a subscription each cycle: line items, subtotal, VAT, total, due date, number. |
| Invoice record | `invoices` table | Immutable statement of what is owed; `status`, `line_items JSON`, `paid_at`. |
| Payment record | `payments` table | Each attempt/result against an invoice; `gateway`, `gateway_reference`, `status`, `raw JSON`. |
| Stored payment method | `payment_methods` table | Tokenized card on file used for renewals (see [15](15-Payment-Gateways.md)). |
| Money movement | Payment gateways | Charge/refund via `PaymentGatewayInterface`; results land back as `payments` + `gateway_events` ([15](15-Payment-Gateways.md)). |
| Dunning | Queued job via cron | Retries overdue invoices, transitions subscription `active ↔ past_due ↔ expired`. |
| Receipts/invoices PDF | View/export | Renders a VAT invoice/receipt for download. |

**How it fits.** A subscription cycle emits one **invoice**; the gateway integration produces one or more **payments** against that invoice; the resulting paid/failed outcome drives the subscription's lifecycle ([13](13-Subscription-System.md)). All three tables are **tenant-scoped** so a company only ever sees its own billing, but `invoices.subscription_id` and `payments.invoice_id` use `ON DELETE SET NULL` so financial history is retained even if the subscription/invoice it referenced is later removed.

## Workflow

### Billing cycle and invoice → payment

```mermaid
sequenceDiagram
    autonumber
    participant Cron as Cron worker (queued_jobs)
    participant B as BillingManager
    participant DB as Database
    participant G as PaymentGateway (see doc 15)
    participant Sub as Subscription

    Cron->>B: renew due subscriptions (ends_at/cycle reached)
    B->>DB: INSERT invoices (number, subtotal, tax=15%, total, status='open', due_at)
    B->>G: charge(payment_method.token, total, currency)
    alt charge succeeds
        G-->>B: success + gateway_reference
        B->>DB: INSERT payments (invoice_id, status='succeeded', gateway_reference, paid_at)
        B->>DB: UPDATE invoices SET status='paid', paid_at=now
        B->>Sub: keep/return status='active'; extend cycle (starts_at/ends_at)
        B->>DB: send receipt (notification + downloadable invoice)
    else charge fails
        G-->>B: failure
        B->>DB: INSERT payments (status='failed', raw=error)
        B->>DB: invoices stays 'open' (now past due)
        B->>Sub: UPDATE subscriptions SET status='past_due'  %% start dunning
    end
```

### Invoice numbering

Invoice numbers are human-readable, monotonic and unique (`invoices.number UNIQUE`). Format: `INV-{YYYY}-{ZeroPaddedSequence}`, e.g. `INV-2026-000123`. The sequence is generated server-side inside the same transaction that creates the invoice (using the max existing sequence for the year, guarded by the unique constraint) so numbers are gapless within reason and never collide.

### Invoice statuses

`invoices.invoice_status_id` → `invoice_statuses` (config-driven; `key`s `draft`, `open`, `paid`, `void`, `uncollectible`):

| Status | Meaning |
|--------|---------|
| `draft` | Being assembled; not yet issued to the customer. |
| `open` | Issued and awaiting payment (the "owed" state); becomes overdue past `due_at`. |
| `paid` | Fully settled; `paid_at` stamped; receipt available. |
| `void` | Cancelled before payment (e.g. erroneous invoice); carries no balance. |
| `uncollectible` | Dunning exhausted; written off (drives `subscription.expired`). |

### Taxes — KSA VAT 15%

Every invoice computes `tax = round(subtotal * 0.15, 2)` and `total = subtotal + tax` in the invoice currency (SAR by default). The VAT rate is configuration (`config('billing.vat_rate', 0.15)`) so a future rate change is data, not code. The 15% line is shown explicitly on the invoice/receipt as a separate VAT line for a compliant tax invoice (فاتورة ضريبية); the company's VAT registration number (when provided in company settings) and HalaOps's seller details are rendered on the document.

### Billing cycle

The cycle is the plan's `interval` (`monthly`/`yearly`) anchored on the subscription's `starts_at`. The renewal job runs on each cycle boundary, issues the next invoice, and attempts payment against the default `payment_methods` token. A successful renewal advances `subscriptions.starts_at`/`ends_at` to the next window.

### Dunning (past_due)

When a renewal fails, the subscription moves to `past_due` and the invoice remains `open`. A dunning schedule retries the charge on a back-off (e.g. day 1, 3, 5, 7) and notifies the owner each time ([26 — Notification System](26-Notification-System.md)). If a retry succeeds, the invoice becomes `paid` and the subscription returns to `active`. If the schedule and grace period are exhausted, the invoice is marked `uncollectible` and the subscription transitions to `expired`.

### Receipts and refunds

On `paid`, a **receipt** (إيصال) is generated and made downloadable, and a notification is sent. A **refund** reverses a succeeded payment via the gateway; it inserts/updates a `payments` row with `status='refunded'` (and a negative/linked amount per gateway semantics), and may `void` or credit the related invoice. Refunds are an Owner-only, audited operation.

## Business Rules

1. **Invoices and payments are separate.** An invoice states what is owed; payments record money movement against it. Never collapse to a single boolean.
2. **VAT is 15%** on the subtotal, computed and stored at issue time (`tax`, `total` columns); the rate is configurable for future changes.
3. **Invoice numbers are unique and monotonic** (`UNIQUE(number)`), assigned server-side in the creating transaction; never editable afterward.
4. **An invoice is immutable once `open`** except for status transitions (`open → paid|void|uncollectible`). Corrections are made by voiding and re-issuing, not by editing amounts.
5. **One invoice per billing cycle** per subscription under normal operation; proration/upgrades may add line items or a one-off invoice.
6. **A successful payment against the cycle invoice keeps the subscription `active`**; a failed one moves it to `past_due` and starts dunning (see [13](13-Subscription-System.md)).
7. **Currency consistency**: an invoice's `currency` equals the subscription's (snapshotted) currency; payments share that currency.
8. **Financial records are retained** beyond subscription/invoice deletion via `SET NULL` FKs — accounting must outlive the tenant relationship.
9. **Refunds are gateway-backed and audited**; HalaOps never silently "marks refunded" without a corresponding gateway action and a `payments` record.
10. **Dunning is bounded**: a fixed retry schedule + grace period; exhausting it marks the invoice `uncollectible` and expires the subscription.
11. **Proration** is computed by the billing layer when a plan changes mid-cycle: a credit for the unused portion of the old plan and a charge for the remainder on the new plan, expressed as invoice line items.

## Database Relations

Consistent with [§11 of the canonical schema](05-Database-Architecture.md):

- **`invoices`** (tenant): `id`, `workspace_id → workspaces(id) ON DELETE CASCADE`, `subscription_id → subscriptions(id) ON DELETE SET NULL`, `number VARCHAR UNIQUE`, `invoice_status_id → invoice_statuses(id) ON DELETE RESTRICT` (config-driven; `key`s `draft`/`open`/`paid`/`void`/`uncollectible`), `subtotal DECIMAL`, `tax DECIMAL`, `total DECIMAL`, `currency`, `due_at`, `paid_at`, `line_items JSON`, timestamps.
- **`payments`** (tenant): `id`, `workspace_id → workspaces(id) ON DELETE CASCADE`, `invoice_id → invoices(id) ON DELETE SET NULL`, `gateway`, `gateway_reference`, `amount DECIMAL`, `currency`, `payment_status_id → payment_statuses(id) ON DELETE RESTRICT` (config-driven; `key`s `pending`/`succeeded`/`failed`/`refunded`), `paid_at`, `raw JSON`, timestamps. Index `payments(workspace_id, gateway_reference)`.
- **`payment_methods`** (tenant): `id`, `workspace_id → workspaces(id) ON DELETE CASCADE`, `gateway`, `token`, `brand`, `last4`, `exp_month`, `exp_year`, `is_default`, timestamps. (Detail in [15](15-Payment-Gateways.md).)
- Upstream: **`subscriptions`** ([13](13-Subscription-System.md)); downstream raw webhook log **`gateway_events`** ([15](15-Payment-Gateways.md)).

`line_items` and `raw` are JSON (gateway-agnostic): `line_items` holds the human-readable breakdown (plan charge, proration credit, etc.); `raw` stores the gateway's verbatim response for reconciliation/audit. The composite index on `(workspace_id, gateway_reference)` makes webhook→payment lookups fast.

## Permissions

| Action | Permission | Default roles |
|--------|-----------|---------------|
| View invoices/receipts, payment history | `billing.view` | owner, admin |
| Pay an open invoice, manage payment methods, refund, change plan | `billing.manage` | **owner only** |

`admin` can **view** billing but cannot **manage** it (`config/rbac.php`). Platform staff reconcile across tenants via `platform.diagnostics` and the planned platform billing tools (see [22 — Super Admin Journey](22-SuperAdmin-Journey.md)). Mutating routes are guarded by `permission:billing.manage` and CSRF.

## Validation

- **Pay invoice**: `invoice_id` `required|integer|exists:invoices,id`, invoice must belong to the active tenant and be `status='open'`; a `payment_method` must exist or be supplied.
- **Amounts**: `subtotal`, `tax`, `total` are server-computed, never accepted from the client; `total = subtotal + tax` is asserted before insert.
- **Currency**: ISO-4217 (3 chars), must match the subscription.
- **Refund**: target payment must be `status='succeeded'`; refund amount `> 0` and `<= payment.amount`; reason recorded.
- **VAT number** (company setting): optional, validated against the KSA 15-digit VAT format when present.
- **Invoice number**: never user-supplied; generated and unique-constrained.

## Edge Cases

1. **Duplicate renewal run** (cron fired twice) — guarded by idempotency: at most one `open` cycle invoice per subscription per period; the second run no-ops. Payment idempotency is enforced at the gateway layer via `gateway_events` ([15](15-Payment-Gateways.md)).
2. **Partial payment** — invoice stays `open` until fully covered; multiple `payments` rows accumulate against it.
3. **Payment succeeds but app crashes before marking the invoice paid** — the gateway **webhook** reconciles state idempotently, marking the invoice `paid` from the event ([15](15-Payment-Gateways.md)).
4. **Refund of a partially-consumed period** — handled as a credit/void per policy; recorded as a `refunded` payment, never a silent deletion.
5. **Currency mismatch** between method and invoice — rejected before charging.
6. **Invoice for a deleted subscription** — `subscription_id` becomes NULL but the invoice/payment history survives for accounting.
7. **Clock skew on `due_at`** — overdue is computed server-side in the invoice currency's billing timezone; never client-trusted.
8. **VAT rounding** — `round(subtotal * rate, 2)` to two decimals; the rounded VAT is what is stored and shown to avoid sub-cent discrepancies.
9. **Dunning exhausted while owner is mid-payment** — a manual payment that succeeds re-activates the subscription and supersedes the pending `uncollectible` transition.

## Security

- **Tenant isolation**: invoices/payments/methods are tenant-scoped and fail closed; one company can never see another's billing. Platform reconciliation uses the audited `withoutTenantScope()` path ([08](08-Multi-Tenant.md)).
- **No client-trusted money**: all amounts/VAT/totals are computed server-side; the client may only reference an `invoice_id` to pay.
- **PCI scope minimised**: no card PAN/CVV is ever stored — only gateway **tokens** in `payment_methods` and non-sensitive `brand`/`last4` (see [15](15-Payment-Gateways.md), [34 — Security](34-Security.md)).
- **Webhook integrity**: payment confirmations are verified and processed idempotently through `gateway_events`; raw payloads stored for audit ([15](15-Payment-Gateways.md)).
- **CSRF** on all billing writes; **audit** to `activity_logs` for invoice issue, payment, refund and write-off, with actor and amount.
- **Authorization**: only the Owner (`billing.manage`) can pay/refund/change plan; viewing requires `billing.view`.
- **Output escaping** of company-supplied fields (e.g. VAT number, name) on rendered invoices.

## Performance

- **Hot path — renewal sweep**: the cron job selects due subscriptions via the indexed `subscriptions(subscription_status_id, ends_at)` and issues invoices in batches; invoice insert + payment is a short transaction.
- **Webhook → payment lookup**: served by the composite index `payments(workspace_id, gateway_reference)` and `gateway_events(gateway, reference)`.
- **Invoice listing** (billing page): paginated, ordered by `created_at DESC`, filtered by the tenant's `workspace_id` (indexed FK).
- **JSON `line_items`/`raw`** avoid extra tables on the read path and are decoded only when an invoice/payment is opened.
- Heavy work (PDF rendering, email receipts, gateway calls) is offloaded to the queue so the request path stays fast.

## Testing

**Unit**
- VAT computation: `subtotal=50.00 → tax=7.50, total=57.50` (15%); rounding to 2 decimals verified.
- Invoice numbering increments per year and never collides (unique constraint honoured under concurrency).
- Status transition guards: `open → paid|void|uncollectible` allowed; illegal transitions rejected.

**Feature (HTTP)**
- Paying an `open` invoice that belongs to the tenant succeeds, stamps `paid_at`, and emits a receipt; paying another tenant's invoice 403s/404s.
- A failed renewal creates a `failed` payment and moves the subscription to `past_due`.
- A successful dunning retry marks the invoice `paid` and the subscription `active`.
- A non-owner with `billing.view` cannot pay/refund (403).

**Security**
- Amounts/VAT cannot be overridden via request fields.
- A webhook replay does not double-apply a payment (idempotent via `gateway_events`).
- Cross-tenant invoice access is blocked by tenant scope.

## Future Expansion

- **ZATCA e-invoicing (Fatoora) compliance**: structured XML + QR on invoices for KSA Phase-2; the `line_items`/invoice fields already capture the needed data.
- **Multi-currency** beyond SAR: `currency` columns already exist; add FX handling and per-currency numbering.
- **Credit notes / account credits**: a `credit_notes` table layered on invoices for refunds-as-credit.
- **Tax flexibility**: `config('billing.vat_rate')` and per-line tax codes for mixed taxable/exempt items.
- **Self-serve billing portal & exportable statements**: CSV/PDF statements via the storage/export layer ([27 — Storage System](27-Storage-System.md)).
- **Configurable dunning policies** per plan (retry cadence, grace length) stored as data.

## Open Questions

None at this time. The exact dunning cadence, grace-period length, and refund-vs-credit policy are business decisions; the schema (invoices/payments + statuses + JSON line items) supports any reasonable choice, and ZATCA e-invoicing is tracked under Future Expansion.
