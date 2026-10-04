# FEATURE SPEC — Workflow Engine

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Workflow Engine · **Layer:** Process · **Implemented in:** Phase 12
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The **Workflow Engine** is HaHireAI's **central automation engine**: it executes
**triggers → conditions → actions**, manages **approvals**, runs a **scheduler**
and **background jobs**, keeps **execution logs**, and supports **versioned
workflows** and **workflow templates** (`MODULES.md`). It is the one place where
automation lives.

The binding rule is that **modules never embed automation**: business modules
(notably **Recruitment**) **publish domain events** and **expose hook points**;
the Workflow Engine **subscribes** to those events and reacts (`MODULES.md` §5;
`APPLICATION_FLOW.md` §8.2). It is therefore **not** a dependency of the modules
whose events it consumes — Recruitment publishes, Workflow subscribes — which
keeps the dependency graph acyclic.

For AI steps, a workflow **invokes the AI Engine via its contract**, requesting a
**capability** — never a provider (`AI_ENGINE.md` §2). Automation **never bypasses
permission checks or tenant isolation**: a workflow acts within a single
workspace's data only, and an automated action is authorized exactly as the
equivalent human action would be (`APPLICATION_FLOW.md` §8.2).

## 2. Scope

### In scope

- **Triggers:** subscriptions to domain events, scheduled times, and manual
  invocation that start a workflow.
- **Conditions:** rule evaluation (rule → action) on trigger context to decide
  whether/which actions run.
- **Actions:** workspace-scoped, permission-checked effects — e.g. request an AI
  capability, send a notification, move an application stage, perform an offer
  action — invoked through the target module's **contract** or by emitting a
  request event the owning module applies.
- **Approvals:** human approval gates within an execution (`WaitingApproval` per
  `STATE_DIAGRAMS.md` §11).
- **Scheduler:** time-based triggers (delays, recurring schedules).
- **Background jobs:** asynchronous execution of long/heavy steps via the queue.
- **Execution logs:** an observable record of each run, its steps, decisions,
  outcomes, and failures.
- **Versioned workflows:** immutable workflow versions so behavior is
  reproducible and changes roll out deliberately.
- **Workflow templates:** reusable starting points that produce ordinary,
  editable workspace workflows.

### Out of scope

- **Business semantics** of the entities it acts on (what a stage means, when an
  offer is valid) — owned by the **Recruitment** module, enforced by its domain
  state machines (`STATE_DIAGRAMS.md`).
- **AI provider/model/prompt/key selection** — owned by the **AI Engine**; the
  Workflow Engine requests **capabilities**.
- **Notification delivery channels** — owned by **Notifications**; the Workflow
  Engine triggers a notification, it does not deliver it.
- **Cross-tenant/platform automation** — out of scope; every workflow is
  workspace-scoped.
- **Embedding automation inside business modules** — forbidden by design.

## 3. Inputs

- **Subscribed domain events** (§7) carrying the trigger context (e.g. an
  application that changed stage), all bearing `workspace_id`.
- **Scheduled-time triggers** from the scheduler (delays, cron-like schedules).
- **Manual invocation** by a permitted user.
- **Workflow definitions:** trigger, conditions, ordered actions, approval gates,
  and the pinned workflow version.
- **Workflow templates** selected to seed a new workflow.
- **AI capability results** returned by the AI Engine for AI steps (advisory).
- **Approval decisions** recorded by permitted approvers.
- **Shared-service inputs:** permission decisions; active `Workspace`; queue;
  target-module contracts.

## 4. Outputs

- **Side-effects within a single workspace**, each **permission-checked**:
  AI capability requests, notification triggers, stage moves, offer actions, and
  other contract-driven effects.
- **Workflow Execution records** with full step-level **execution logs**.
- **Workflow domain events** (§7) for downstream consumers (Audit, Reports,
  Notifications).
- **Approval requests** routed to permitted approvers and resolved decisions.
- **Scheduled background jobs** enqueued for asynchronous steps.
- **Action-request events** (e.g. `workflows.workflow.action_requested`) that the
  owning module applies through its own authorized, tenant-guarded path.

## 5. Dependencies (modules + contracts consumed; shared services used)

| Dependency | Type | Why |
|---|---|---|
| **Core Kernel** | Foundation | Container, **Event Dispatcher**, config, logging, queue/jobs. |
| **Database** | Foundation | Persistence for workflows, versions, executions, logs. |
| **Event Bus** | Shared service | Subscribe to domain events; publish workflow events (`MODULES.md` §5). |
| **AI Engine** | Contract (capability) | Invoke AI steps as **capabilities**, never providers (`AI_ENGINE.md`). |
| **Notifications** | Contract | Trigger notifications as an action. |
| **Permissions** | Contract | Authorize `workflow.*` and every automated action (deny-by-default). |
| **Workspaces** | Contract | Tenant scope for every workflow, execution, and action. |
| **Recruitment** (and any module) | Contract / events | Subscribe to events; invoke published contracts to perform actions; **Recruitment does not depend on Workflow** (`MODULES.md` §5). |
| **Audit** | Shared service / events | Record significant automation activity. |

Per `MODULES.md` §5, the Workflow Engine depends on the **Event Bus, AI Engine,
Notifications, and (any module via contracts)** — and breaks would-be cycles by
**subscribing to events** rather than being depended upon by event publishers.

## 6. Permissions (keys this module declares)

Grammar `resource.action` (lowercase, dot-separated; `PERMISSION_MODEL.md` §2).
Workspace permissions, gated by subscription + enabled modules; deny-by-default.

- `workflow.view` — view workflows, versions, and execution logs.
- `workflow.create` — create a workflow.
- `workflow.update` — edit a workflow (produces a new version).
- `workflow.delete` — delete/disable a workflow.
- `workflow.publish` — activate a workflow version.
- `workflow.run` — manually invoke a workflow.
- `workflow.approve` — resolve an approval gate (`WaitingApproval` → proceed).

**Binding constraint:** every **action** a workflow performs is **additionally**
gated by the permission of the *equivalent human action* in the target module
(e.g. moving a stage requires `application.move`; running an AI interview requires
`interview.ai.run`; sending an offer requires `offer.send`). A workflow can never
perform an action that a human in that context could not (`APPLICATION_FLOW.md`
§8.2). System-level automation administration, if any, is gated by `system.*` in
the Platform Context.

## 7. Events (Published / Subscribed)

Grammar `<module>.<entity>.<event>`, past tense (`PROJECT_CONSTITUTION.md` §7).
The workflow-execution lifecycle states are defined in `STATE_DIAGRAMS.md` §11
(Pending → Running → Completed / Failed / WaitingApproval / Cancelled).

### Published

- `workflows.workflow.created`
- `workflows.workflow.updated`
- `workflows.workflow.published`
- `workflows.execution.started`
- `workflows.execution.completed`
- `workflows.execution.failed`
- `workflows.execution.waiting_approval`
- `workflows.execution.cancelled`
- `workflows.workflow.action_requested` — a request for an owning module to apply
  a permission-checked action (e.g. a stage move) through its own authorized path.

### Subscribed

Triggers come from other modules' events, for example
(`APPLICATION_FLOW.md` §8.2):

- `applications.application.submitted` (Recruitment) — auto-acknowledge, enqueue
  AI screening.
- `applications.application.stage_changed` (Recruitment) — notify hiring team,
  schedule next interview.
- `interviews.interview.completed` (Recruitment) — request an AI summary, move
  stage if a rule matches.
- `offers.offer.accepted` (Recruitment) — trigger onboarding / Employee-context
  creation.
- `jobs.job.closed` (Recruitment) — archive pipeline, notify applicants.
- `ai.session.completed` (AI Engine) — continue a workflow awaiting an AI result.

A workflow MAY subscribe to **any** module's published events; it MUST NOT read
another module's tables (`ARCHITECTURE.md` §4).

## 8. Data Owned (conceptual entities only — defer to `DATABASE_ARCHITECTURE.md`)

All entities are **workspace-scoped** (carry `workspace_id`) with a ULID `id`
(`CHAR(26)`), isolated per tenant.

- **Workflow** *(aggregate root)* — a named automation: trigger + conditions +
  ordered actions + approval gates.
- **Workflow Version** — an immutable, pinnable version of a workflow so behavior
  is reproducible and auditable.
- **Trigger** — the event/schedule/manual entry point of a workflow.
- **Condition / Rule** — a rule evaluated against trigger context.
- **Action** — a single permission-checked effect (AI capability, notification,
  stage move, offer action, …) defined as data.
- **Workflow Execution** *(aggregate root)* — one run of a workflow; state per
  `STATE_DIAGRAMS.md` §11.
- **Execution Step / Log Entry** — an observable, append-only record of each step,
  decision, and outcome within an execution.
- **Approval Request** — a human approval gate and its resolution.
- **Scheduled Job** — a time-based or queued background job created by the engine.
- **Workflow Template** — a reusable definition that seeds an ordinary, editable
  workspace workflow.

Workflow definitions, conditions, and actions are **data, not code** — mirroring
"roles are data" / "stages are data"; the engine never branches on a workflow's
name (`PERMISSION_MODEL.md`; `APPLICATION_FLOW.md` §5).

## 9. Acceptance Criteria (testable checklist)

- [ ] Business modules **publish events**; the Workflow Engine **subscribes** and
      reacts. The engine is **not** a compile-time dependency of any event
      publisher (e.g. Recruitment) — verified by dependency check.
- [ ] A workflow executes the **trigger → conditions → actions** model; an action
      runs only when its conditions match.
- [ ] **Every automated action is permission-checked** with the same permission as
      the equivalent human action, and is **tenant-guarded** to a single
      workspace (`APPLICATION_FLOW.md` §8.2).
- [ ] A workflow **never bypasses** a domain state machine: e.g. an automated
      stage move or offer action is rejected if the equivalent manual transition
      is forbidden (`STATE_DIAGRAMS.md`).
- [ ] AI steps invoke the **AI Engine contract** requesting a **capability**; no
      provider/SDK is called from the Workflow Engine.
- [ ] AI results consumed by a workflow are **advisory**; a workflow does not turn
      an advisory result into an irreversible action without the appropriate
      permission (human-in-the-loop preserved; `AI_ENGINE.md` §8.1).
- [ ] A **Workflow Execution** follows `STATE_DIAGRAMS.md` §11
      (Pending/Running/Completed/Failed/WaitingApproval/Cancelled), including
      retry from Failed and resume from approval.
- [ ] **Approval gates** pause execution at `WaitingApproval` and require
      `workflow.approve` to proceed.
- [ ] The **scheduler** fires time-based triggers; long/heavy steps run as
      **background jobs** via the queue without blocking web requests
      (`PROJECT_CONSTITUTION.md` §11).
- [ ] Each run produces an **execution log** of steps, decisions, outcomes, and
      failures, observable for troubleshooting.
- [ ] Workflows are **versioned**; editing produces a new version and running
      pins a version for reproducibility.
- [ ] **Templates** produce ordinary, editable workspace workflows — they are not
      special in code.
- [ ] Significant automation activity is **audited** via the shared `Audit`
      service.
- [ ] Every workflow, version, execution, and log entry carries `workspace_id`
      and a ULID `id`; all access is tenant-guarded at the Infrastructure layer.
- [ ] The engine **degrades gracefully** when an optional target module is
      disabled (the affected action is skipped/flagged, not crashed).
- [ ] The module exposes behavior **only** through its `Contracts` surface and
      published events.

### Related Documents

`MODULES.md` · `ARCHITECTURE.md` · `DOMAIN_MODEL.md` · `APPLICATION_FLOW.md` ·
`STATE_DIAGRAMS.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`AI_ENGINE.md` · `DATABASE_ARCHITECTURE.md` ·
`FEATURE_SPECIFICATIONS/Recruitment.md` · `FEATURE_SPECIFICATIONS/AI_Engine.md` ·
`FEATURE_SPECIFICATIONS/Reports_Analytics.md`
