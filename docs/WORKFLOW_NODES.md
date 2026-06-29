# WORKFLOW_NODES — HaHireAI

The node catalog (`app/Modules/Workflow/Domain/NodeCatalog.php`) is the single
source of truth shared by the builder (palette, node cards, config panels, variable
picker, summary) and the engine (`ActionExecutor` dispatch). Pure data — no logic.

Every node declares: `type`, `category`, `kind` (trigger | condition | action),
`label`, `description`, `icon`, a `config` schema rendered as **plain form fields
(no JSON, no code)**, and — for triggers — the existing event it binds to.

## Categories (palette order)

`Triggers · Actions · Conditions · AI · Recruitment · Workspace · Users ·
Notifications · Database · Files · Time · Logic · Variables · Integrations ·
Utilities`

## Config field types

| type | UI control |
|---|---|
| `text` | single-line input |
| `select` | dropdown of fixed options |
| `variable` | input + datalist of `{{trigger outputs}}` (a token picker, not code) |
| `formula` | textarea evaluated by the **sandboxed** `FormulaEvaluator` |

## Triggers

Bound to events that already exist (`WORKFLOW_EVENTS.md`): Candidate Applied,
Interview Scheduled, Interview Finished, Pipeline Changed, Candidate Hired/Rejected
(add an `If` on status), Offer Sent/Accepted/Declined, Workspace
Created/Suspended/Restored, User Joined, Subscription Renewed, Payment Failed, plus
Manual / Webhook / Schedule.

## Actions — execution status

Effectful nodes call **only existing services via Core contracts**. Three tiers:

- **Fully wired** (run real effects):
  - AI: `ai.*` → the central **AI Engine** (capability-mapped).
  - Tasks: `workspace.create_task` → `TaskWriter`.
  - Notifications: `users.notify`, `notify.in_app`, `workspace.create_notification`
    → `NotificationWriter`.
  - Recruitment: `recruitment.move_candidate` / `update_stage`, `reject_candidate`,
    `hire_candidate`, `archive_candidate` → `RecruitmentActions`.
  - Database: `db.create_record`, `db.count_records` → Dynamic Collections.
  - Logic/Utilities: `logic.formula`, `variables.set`, `util.log`, `util.audit`,
    `workspace.create_activity`, `util.noop`.
- **Conditions:** `condition.if`, `condition.switch`, `logic.filter` — branch on a
  field comparison (engine attaches the test to following steps).
- **Provider-gated** (selectable, but report `skipped: no configured provider` until
  the integration exists): email, Slack/Teams/webhook, outbound HTTP, time
  delays/waits, file attach, invite/assign. They never silently “succeed”.

## Variables & tokens

Config values may contain `{{field}}` tokens inserted by the variable picker. The
engine substitutes them from the evolving payload (trigger outputs + any
Set-Variable/Formula results). Users never write code to reference data.

See `WORKFLOW_ENGINE.md` for how steps execute.
