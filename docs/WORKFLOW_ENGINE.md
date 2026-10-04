# WORKFLOW ENGINE — HaHireAI

> **Status:** Implemented (Phase 12) · **Version:** 1.0.0 · **Last updated:** 2026-06-28
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `AI_ENGINE.md`.

---

## 1. Purpose & Scope

The **Workflow Engine** is the central automation and business-process layer of
HaHireAI. Long-running, multi-step, event-driven business processes run **here**,
never inside feature modules. A module's job is to do its work and **publish a
domain event**; the Workflow Engine's job is to **react** — running any
workspace-defined automation that subscribes to that event.

This is the Phase-12 canonical specification: it defines the event model, the
workflow-as-data model, the action catalog, execution recording, and the
isolation and decoupling guarantees every module MUST honor.

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119, per
`PROJECT_CONSTITUTION.md`.

---

## 2. Principle: Actors Publish, Reactors Subscribe

The architecture mandates that modules communicate only through **Contracts,
Events, and Shared Services** — never by reaching into each other's internals
(`ARCHITECTURE.md` §4). The Workflow Engine is the canonical **reactor**.

```
  ┌──────────────────────────────────────────────────────────────────┐
  │  ACTOR module          EVENT (in-process bus)        REACTOR       │
  │                                                                    │
  │  Recruitment ──"application.submitted"──▶ EventDispatcher          │
  │  (apply())                                     │                   │
  │                                                ▼                   │
  │                                       ┌─────────────────┐          │
  │                                       │ Workflow Engine │          │
  │                                       └────────┬────────┘          │
  │                                                │ runs matching     │
  │                                                ▼  workflows        │
  │                                   audit ▸ run_ai ▸ … (actions)     │
  └──────────────────────────────────────────────────────────────────┘
```

Binding consequences:

- The actor module (e.g. Recruitment) **MUST NOT** know that automations exist.
  It publishes `application.submitted` and returns. Whether zero or ten workflows
  react is invisible to it.
- The Workflow Engine **MUST NOT** be called directly by feature modules to "run
  my automation." It is reached **only** through events.
- This decoupling is what lets a workspace add, edit, or disable automations with
  **no change to any feature module**.

---

## 3. The Event Model

Events are dispatched in-process and synchronously through the
`EventDispatcher` contract (`app/Core/Contracts/EventDispatcher.php`). The
Phase-13 async/external bus subscribes through the same mechanism, so callers
never change.

| Event | Published by | Payload (illustrative) |
|---|---|---|
| `application.submitted` | Recruitment · `PublicJobController::apply()` | `workspace_id`, `application_id`, `job_id`, `job_title`, `user_id`, `candidate_name`, `candidate_email` |

> The catalog grows as modules publish more events (e.g. `offer.sent`,
> `employee.hired`). Adding an event is additive: publish it from the actor, then
> let workspaces bind workflows to it. **No reactor code change is required** —
> the engine matches workflows to the trigger string generically.

Every payload **MUST** carry `workspace_id`; the engine ignores any event that
does not (tenant scope is mandatory — §6).

---

## 4. Workflows Are Data

A workflow is **stored data**, not code — the same rule as "roles are data" and
"no hard-coded pages." Automations are created, enabled, and disabled at runtime
by users with the right permission, never by deployment.

A workflow row (`workflows` table) holds:

| Field | Meaning |
|---|---|
| `name` | Human label. |
| `trigger_event` | The event string it reacts to (e.g. `application.submitted`). |
| `definition` | JSON `{ steps: [ { action, params, condition? } ] }`. |
| `status` | `draft` \| `published` — only `published` workflows run. |
| `enabled` | Boolean kill-switch, independent of status. |
| `version` | Incremented as a definition evolves. |

Only workflows that are **`enabled = 1` AND `status = 'published'` AND not
soft-deleted AND matching both `workspace_id` and `trigger_event`** are eligible
to run (`WorkflowService::findEnabledForTrigger`).

### 4.1 Steps, actions, conditions

A `definition` is an ordered list of **steps**. Each step names an **action**,
optional **params**, and an optional **condition**. Conditions are evaluated
against the event payload; an unmet condition **skips** that step (recorded as
`skipped`) without failing the run. Supported operators: `equals`, `not_equals`,
`contains`, `exists`.

---

## 5. Action Catalog

An **action** is a single provider-agnostic unit of automation work
(`ActionExecutor`). Phase-12 actions:

| Action | What it does | Notes |
|---|---|---|
| `log` | Returns a message into the step output. | Side-effect-free; useful for tracing. |
| `audit` | Records an entry via the shared `Audit` service. | who/what/when/which workspace. |
| `run_ai` | Requests an **AI capability** from the central AI Engine. | e.g. `summarize_candidate`. **Never** calls a provider directly (§6). |

Unknown actions resolve to a safe `noop` rather than throwing, so a
forward-defined definition never hard-fails an execution.

> **AI is requested, never embedded.** The `run_ai` action calls
> `AiEngine::run(workspaceId, capability, variables)` and stores a short
> reference to the result. Provider selection, prompting, fallback, and cost
> accounting all remain the AI Engine's concern (`AI_ENGINE.md` §2). The Workflow
> Engine MUST NOT embed prompts, models, keys, or provider transport.

---

## 6. Execution & Recording

When a trigger fires, `WorkflowEngine::runForTrigger` loads every eligible
workflow and runs each as an **execution**:

```
  runForTrigger(workspaceId, triggerEvent, payload, actor)
        │  for each enabled+published workflow on this trigger
        ▼
  workflow_executions  (status=running → completed | failed)
        │  for each step
        ▼
  workflow_steps  (status = completed | skipped | failed, + output)
```

- Each execution is recorded in `workflow_executions` with `steps_total`,
  `steps_done`, `status`, the triggering `payload`, and `started_at` /
  `finished_at`.
- Each step is recorded in `workflow_steps` with its `step_index`, `action`,
  `status`, and a truncated `output` (≤ 2000 chars — never a place for secrets or
  full PII).
- A throwing step marks the step `failed`, marks the execution `failed` with the
  error, and stops that workflow — other workflows on the same trigger are
  unaffected.
- Execution is **synchronous** in Phase 12 but **queue-ready**: the engine is the
  single place where async dispatch is introduced later, with no caller change
  (`ARCHITECTURE.md` §8).

### 6.1 Tenant isolation (critical)

- Every workflow, execution, and step carries `workspace_id`.
- The engine matches workflows **scoped to the event's `workspace_id`**. Firing a
  trigger in workspace B **MUST NOT** run workspace A's workflows. This is
  covered by an explicit isolation test (`WorkflowEngineTest`).
- Cross-workspace execution or visibility of automations is a **critical security
  defect** (`WORKSPACE_MODEL.md` §3, `PROJECT_CONSTITUTION.md` §10).

---

## 7. Permissions

Workflow management is permission-gated, deny-by-default, checked by **key**
(never role name), per `PERMISSION_MODEL.md`:

| Key | Grants |
|---|---|
| `workflow.view` | View workflows & executions; reveals the **Workflows** sidebar item. |
| `workflow.create` | Create a workflow. |
| `workflow.update` | Edit a workflow. |
| `workflow.delete` | Delete a workflow. |
| `workflow.execute` | Manually run a workflow. |

The sidebar exposes **Workflows** only when `workflow.view` is held — the single
dynamic sidebar remains a pure function of context + permissions
(`SIDEBAR_MODEL.md`).

---

## 8. How a Module Triggers Automation

A module triggers automation by **publishing an event**, nothing more:

```php
// Recruitment — PublicJobController::apply(), AFTER the application is stored.
$this->events->dispatch('application.submitted', [
    'workspace_id'    => (string) $job['workspace_id'],   // mandatory tenant scope
    'application_id'  => $applicationId,
    'job_id'          => (string) $job['id'],
    'candidate_name'  => (string) ($applicant['name'] ?? ''),
    // …
]);
```

The module does **not**: look up workflows, run steps, call AI, or write
executions. The `WorkflowModule` registers the listener at boot
(`WorkflowModule::boot`), and the engine does the rest. This is the contract that
keeps automation **central infrastructure** rather than scattered feature code.

---

## 9. Acceptance (Phase 12)

Verified by `tests/Feature/WorkflowEngineTest.php` against a live MySQL 8
database:

- ✅ A trigger runs the matching workflow and records an execution with per-step
  rows; the `run_ai` step routes through the central AI Engine (an `ai_sessions`
  row is created).
- ✅ Only `enabled` + `published` workflows bound to the **same trigger** run.
- ✅ Workflows are **isolated per workspace** (B's trigger never runs A's flow).
- ✅ A step whose **condition** is unmet is `skipped`, not failed.
- ✅ The `WorkflowModule` boot listener runs a workflow when the event is
  dispatched through the **real** `EventDispatcher` + container — proving the
  end-to-end actor→event→reactor path.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `AI_ENGINE.md` · `MODULES.md` ·
`DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`ENTITY_CATALOG.md` · `EVENT_BUS.md` (Phase 13)
