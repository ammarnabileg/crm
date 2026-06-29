# WORKFLOW_EVENTS — HaHireAI

How workflows fire. Triggers bind **only** to events that already happen in the
system — the engine never polls.

## Two event sources

1. **Domain events** dispatched directly on the bus — today: `application.submitted`
   (published by the public/portal apply flow).
2. **Bridged audit events.** Every controller records an audit entry through the
   `AuditRecorder` contract. `AuditTriggerBridge` **decorates** that contract: it
   records as before, then re-publishes the entry on the bus as `audit.{action}`.
   This turns the whole lifecycle into triggers **without modifying any service**.

`WorkflowModule::boot()` subscribes one listener per unique trigger event (from
`NodeCatalog::triggerBindings()`) and runs all matching workflows.

## Trigger → event map

| Trigger node | Event |
|---|---|
| Candidate Applied | `application.submitted` |
| Interview Scheduled | `audit.recruitment.interview.scheduled` |
| Interview Finished | `audit.recruitment.interview.evaluated` |
| Pipeline Changed / Candidate Hired / Rejected | `audit.recruitment.application.status_changed` |
| Offer Sent / Accepted / Declined | `audit.recruitment.offer.{sent,accepted,declined}` |
| Workspace Created | `audit.workspaces.workspace.created` |
| Workspace Suspended / Restored | `audit.platform.workspace.{suspended,activated}` |
| User Joined Workspace | `audit.memberships.invitation.accepted` |
| Subscription Renewed | `audit.billing.subscription.renewed` |
| Payment Failed | `audit.billing.payment.failed` |
| Manual / Webhook / Schedule | run on demand / future |

Hired/Rejected share the status-changed event — add an **If** on the status field to
distinguish them. Triggers whose audit action isn't recorded yet stay selectable and
fire automatically once that action exists.

## Payload

The bridge builds a workspace-scoped payload from the audit context:
`workspace_id`, `user_id` (actor), `entity_type`, `entity_id`, an alias
`{entity_type}_id` (e.g. `application_id`), and any scalar `changes` flattened
(e.g. `to_status`). Nodes read these via `{{tokens}}`.

## Loop safety

The bridge **never** re-publishes the engine's own `workflows.*` audit actions, so a
workflow whose action writes audit can't trigger itself. Workflow actions call
services directly (not controllers), so they don't emit audit and can't cascade.

See `WORKFLOW_ENGINE.md` and `WORKFLOW_NODES.md`.
