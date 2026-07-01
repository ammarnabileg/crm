# FEATURE SPEC — Recruitment

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Recruitment · **Layer:** Business Domain · **Implemented in:** Phase 10
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The **Recruitment** module is HaHireAI's **hiring operating system**: a single
bounded context that owns the entire lifecycle of a hire, from a published `Job`
to an `Employee` context. It is **one module**, internally partitioned into
cohesive **sub-domains** — `Jobs`, `Applications`, `Candidate Profiles`,
`Pipeline`, `Interviews` (AI + human), `Offers`, `Talent Pool`, `Templates`,
`Hiring Analytics`, `Activity Timeline`, and `Automation Hooks` — that share the
Recruitment context and communicate internally, exposing a **single `Contracts`
surface** to the rest of the platform (`MODULES.md` §3).

Per the Constitution, hiring is treated as an **operational discipline**, not a
sequence of forms: every job, application, candidate, interview, and offer is
part of one intelligent, observable system. The module MUST embody three binding
ideas from `APPLICATION_FLOW.md`:

1. **The `Application` is the spine** — it binds `User` + `Job` + `Workspace` and
   is the single source of truth for one candidacy.
2. **The `Candidate Profile` is a per-`(User, Workspace)` view**, never an
   account and never cross-workspace.
3. **AI advises; humans decide** — every AI contribution is advisory and a
   permitted human can always override it.

This module **requests AI behavior as capabilities** from the **AI Engine** and
**publishes domain events** that the **Workflow Engine**, **Notifications**,
**Search**, **Audit**, and **Reports/Analytics** subscribe to. It depends on
**none** of those reactive modules (`MODULES.md` §5).

## 2. Scope

### In scope

- **Jobs:** requisition builder, lifecycle (Draft → Published → Paused → Closed →
  Archived), public job page (no login), custom screening questions, required
  documents, hiring team assignment, per-job pipeline selection.
- **Applications:** the candidacy engine binding `User` + `Job` + `Workspace`;
  application statuses per `STATE_DIAGRAMS.md` §3; document references; AI work
  enqueue; immutable Activity Timeline.
- **Candidate Profiles:** workspace-scoped projection of a `User`, carrying
  notes, tags, ratings, scorecards, AI insights, and timeline — **for that
  workspace only**.
- **Pipeline:** Kanban board, customizable per-workspace (and optionally
  per-job) stages, drag/drop moves, bulk actions, saved views/filters.
- **Interviews:** scheduling and conduct of **AI** and **human** interviews,
  scorecards, transcripts, evaluation aggregation; states per
  `STATE_DIAGRAMS.md` §5.
- **Offers:** formal hiring proposals with approval and lifecycle per
  `STATE_DIAGRAMS.md` §4.
- **Talent Pool:** workspace collection of saved/passive/past candidates and
  silver-medalist re-engagement. **Smart Segments** — named, saved candidate
  filters (rules combined by ALL/AND or ANY/OR over Skill, Language, min Score,
  last-interview recency, Available, Status, Seniority) that evaluate over the
  workspace's candidates and bulk-add matches into a pool. Managed with the
  existing `talent.view` / `talent.manage` permissions (no new key).
- **Templates:** reusable job descriptions, pipeline templates, screening
  question sets, scorecard templates, and message templates.
- **Hiring Analytics:** recruitment-domain metrics surfaced for in-context views
  (funnel, time-in-stage, source) — authored here, aggregated and rendered by
  **Reports/Analytics**.
- **Employee context creation:** on `Hired`, the `User` gains an `Employee`
  context (states per `STATE_DIAGRAMS.md` §6); onboarding side-effects are driven
  by the **Workflow Engine**.

### Out of scope

- **AI provider/model selection, prompts, keys, streaming, cost** — owned by the
  **AI Engine** (`AI_ENGINE.md`). Recruitment requests **capabilities only**.
- **Automation execution** (triggers/conditions/actions/approvals/scheduling) —
  owned by the **Workflow Engine** (`MODULES.md` Phase 12). Recruitment **emits
  events and exposes hook points**; it MUST NOT embed automation logic.
- **Cross-module dashboards, exports, saved cross-domain views** — owned by
  **Reports/Analytics**.
- **File storage, virus scanning, retention** — owned by **Files**.
- **Notification delivery channels** — owned by **Notifications**.
- **The `User` identity, authentication, memberships, roles** — owned by
  Identity & Access modules. Recruitment never creates a "candidate account."
- **Subscription/plan gating and tenant limits** — owned by
  **Licensing/Subscriptions**.

## 3. Inputs

- **Job authoring input:** title, description (raw or AI-drafted), location,
  employment type, compensation band, department, custom screening questions,
  required documents, selected pipeline, hiring team members.
- **Public application input:** answers to screening questions and one or more
  uploaded documents (CV/resume), submitted from the public job page; resolves to
  a single `User` identity via register/sign-in during apply.
- **Pipeline operations:** stage definitions, stage moves (single, drag/drop,
  bulk), filter/sort criteria, saved-view definitions.
- **Evaluation input:** human scorecards, ratings, notes, tags; interview
  scheduling parameters; AI interview configuration (capability request, not
  provider).
- **Offer input:** offer terms, approver(s), validity window, decision events
  (approve/send/accept/decline/expire/revoke).
- **AI capability results (advisory):** structured outputs returned by the AI
  Engine (resume parse, CV analysis, interview score/summary, candidate summary,
  hiring recommendation, comparison) — consumed as recommendations.
- **Shared-service inputs:** authenticated `User` and active `Workspace` context;
  permission decisions; `File` references; subscription/enabled-module state.

## 4. Outputs

- **Persisted recruitment entities** (workspace-scoped): jobs, applications,
  candidate-profile evaluative data, pipeline/stage definitions, interviews,
  offers, talent-pool entries, templates, and append-only timeline entries.
- **A public, login-free job page** for each `Published` job, accepting new
  applications.
- **Recruitment domain events** (§7) published to the Event Bus for downstream
  modules.
- **Capability requests to the AI Engine** carrying context/variables only.
- **Read models / view-models** for the Kanban board, candidate profile,
  job dashboard, and in-context hiring analytics.
- **Contract responses** to other modules (e.g. application/job lookups, hiring
  status) via the Recruitment `Contracts` surface.
- **Audit and search signals** emitted via shared services / events.

## 5. Dependencies (modules + contracts consumed; shared services used)

| Dependency | Type | Why |
|---|---|---|
| **Core Kernel** | Foundation | Container, router, events, config, logging. |
| **Database** | Foundation | Connection, repositories, transactions, tenant-guarded persistence. |
| **Permissions** | Contract | Deny-by-default authorization at the Application boundary (§6). |
| **Workspaces** | Contract | Active workspace context; tenancy root for every record. |
| **Memberships / Users** | Contract | Resolve the single `User` identity and hiring-team members (read-only). |
| **Files** | Contract (shared service) | CV/resume and attachments — **referenced, never copied** (`APPLICATION_FLOW.md` §4). |
| **AI Engine** | Contract (capability) | Requests capabilities (CV analysis, resume parsing, AI interview, candidate summary, hiring recommendation, comparison, JD generation, interview questions, email/translation). Never a provider. |
| **Settings** | Contract | Workspace recruitment settings and defaults. |
| **Search** | Event/contract (optional) | Indexing of jobs/candidacies; degrades gracefully if disabled. |
| **Notifications** | Event (subscriber) | Reacts to recruitment events; **not** a dependency of Recruitment. |
| **Workflow Engine** | Event (subscriber) | Subscribes to recruitment events for automation; **not** a dependency. |
| **Reports / Analytics** | Event/contract (consumer) | Aggregates hiring metrics from events/read contracts. |
| **Audit** | Shared service / event | Records significant recruitment actions. |

**Dependency-rule compliance:** Recruitment depends only on lower/contract
surfaces and **publishes events** for reactors, keeping the graph acyclic
(`MODULES.md` §5). Optional dependencies (Search) degrade gracefully when
disabled (`PROJECT_CONSTITUTION.md` §9).

## 6. Permissions (keys this module declares)

Grammar is `resource.action` / `resource.subresource.action`, lowercase,
dot-separated (`PERMISSION_MODEL.md` §2). All are **workspace** permissions,
gated by subscription + enabled modules. Deny-by-default applies to every key.

**Jobs**

- `job.view` — view jobs and the job dashboard.
- `job.create` — create a job (Draft).
- `job.update` — edit job details, questions, required documents, hiring team.
- `job.publish` — transition Draft → Published.
- `job.pause` — transition Published → Paused.
- `job.resume` — transition Paused → Published.
- `job.close` — transition Published/Paused → Closed.
- `job.archive` — transition Closed/Draft → Archived (and restore).
- `job.delete` — soft-delete a job where permitted.

**Candidate Profiles**

- `candidate.view` — view the workspace-scoped candidate profile.
- `candidate.update` — edit notes, tags, ratings on the profile.
- `candidate.note.manage` — create/edit/remove notes and comments.
- `candidate.tag.manage` — manage tags applied within the workspace.
- `candidate.export` — export candidate data (PII-gated, audited).

**Applications**

- `application.view` — view applications and their timeline.
- `application.create` — create/record an application on behalf of a candidate.
- `application.update` — edit application data and document references.
- `application.move` — change an application's stage/status (drag/drop, single).
- `application.reject` — transition to Rejected.
- `application.withdraw` — record a Withdrawn outcome.
- `application.bulk` — perform bulk operations across selected applications.

**Pipeline**

- `pipeline.view` — view the Kanban board and saved views.
- `pipeline.manage` — create/rename/reorder/remove stages and manage saved views.

**Interviews**

- `interview.view` — view interviews, scorecards, transcripts.
- `interview.schedule` — create/reschedule/cancel interviews.
- `interview.evaluate` — record scorecards/ratings; mark Evaluated.
- `interview.ai.run` — request an **AI Interview** capability (sub-resource form,
  canonical in `PERMISSION_MODEL.md` §2).

**Offers**

- `offer.view` — view offers.
- `offer.create` — draft an offer.
- `offer.approve` — approve an offer (Draft → Approved).
- `offer.send` — send an approved offer (Approved → Sent).
- `offer.revoke` — revoke a sent/approved offer.

**Talent Pool**

- `talent.view` — view the talent pool.
- `talent.manage` — add/remove/tag talent-pool entries and re-engage candidates.

**Templates**

- `template.view` — view recruitment templates.
- `template.manage` — create/update/delete JD, pipeline, question, and scorecard
  templates.

**Employee (post-hire context)**

- `employee.view` — view the post-hire employee context.
- `employee.manage` — manage employee-context lifecycle transitions (onboarding →
  active → offboarding → terminated) where Recruitment owns the trigger.

System-level recruitment administration, if any, is gated by `system.*`
permissions in the Platform Context and is **out of scope** for this module
(`PERMISSION_MODEL.md` §6).

## 7. Events (Published / Subscribed)

Event grammar is `<module>.<entity>.<event>`, **past tense**
(`PROJECT_CONSTITUTION.md` §7; canonical example `applications.application.submitted`).
Recruitment events use the **sub-domain name** as the prefix.

### Published

**Jobs**

- `jobs.job.created`
- `jobs.job.published`
- `jobs.job.paused`
- `jobs.job.resumed`
- `jobs.job.closed`
- `jobs.job.archived`

**Applications**

- `applications.application.submitted`
- `applications.application.stage_changed`
- `applications.application.rejected`
- `applications.application.withdrawn`

**Candidate Profiles**

- `candidates.candidate_profile.updated`
- `candidates.candidate.note_added`
- `candidates.candidate.tagged`

**Interviews**

- `interviews.interview.scheduled`
- `interviews.interview.started`
- `interviews.interview.completed`
- `interviews.interview.evaluated`
- `interviews.interview.cancelled`

**Offers**

- `offers.offer.drafted`
- `offers.offer.approved`
- `offers.offer.sent`
- `offers.offer.accepted`
- `offers.offer.declined`
- `offers.offer.revoked`

**Employee**

- `employees.employee.created`
- `employees.employee.activated`
- `employees.employee.offboarded`
- `employees.employee.terminated`

**Talent Pool**

- `talent.talent_entry.added`
- `talent.talent_entry.removed`

> Naming note: `APPLICATION_FLOW.md` §3 also illustrates `recruitment.job.*` for
> job transitions. This spec adopts the sub-domain prefix to match the canonical
> `applications.application.submitted` example exactly; both forms refer to events
> published by the single Recruitment module.

### Subscribed

Recruitment is primarily a **publisher**. It MAY subscribe to a small set of
events to keep its own state consistent, never to embed automation:

- `ai.session.completed` (AI Engine) — to attach advisory results to the relevant
  application/interview/candidate profile when an asynchronous capability
  finishes.
- `files.file.scanned` (Files) — to mark a referenced document as safe/usable.
- `workflows.workflow.action_requested` (Workflow Engine) — to apply an
  automated, **permission-checked** stage move or offer action requested by a
  workflow (the human-equivalent action still passes authorization and tenant
  guards; `APPLICATION_FLOW.md` §8.2).

All transitions triggered by subscribed events MUST pass the same permission
checks and tenant guards as direct user actions (`APPLICATION_FLOW.md` §10.6).

## 8. Data Owned (conceptual entities only — defer to `DATABASE_ARCHITECTURE.md`)

All entities are **workspace-scoped** (carry `workspace_id`), use a ULID `id`
(`CHAR(26)`), and are isolated per tenant unless noted. Schema, indexes, and FKs
are defined in `DATABASE_ARCHITECTURE.md`.

- **Job** *(aggregate root)* — requisition + public posting + hiring team +
  selected pipeline; lifecycle state per `STATE_DIAGRAMS.md` §2.
- **Job Question** — a custom screening question attached to a job.
- **Job Required Document** — a document the job requires from applicants.
- **Application** *(aggregate root)* — the spine binding `User` + `Job` +
  `Workspace`; status per `STATE_DIAGRAMS.md` §3; owns its timeline and document
  references. References (never copies) the `User` and the user-owned CV `File`.
- **Application Document Reference** — a link from an application to a user-owned
  `File`.
- **Activity Timeline Entry** — append-only, immutable record of actions/events
  on an application (integrates with **Audit**).
- **Candidate Profile** *(per-`(User, Workspace)` projection)* — workspace-scoped
  evaluative data: notes, tags, ratings, scorecards, AI insights, applications
  **in this workspace only**. Never an account; never cross-workspace.
- **Candidate Note / Tag / Rating / Scorecard** — workspace-scoped evaluative
  artifacts attached to the candidate profile.
- **Pipeline** — an ordered set of stages, defined per workspace (and optionally
  per job).
- **Stage** — a single customizable column/step; **data, not code** (no logic
  branches on a stage's name).
- **Saved View** — a stored Kanban filter/sort/column configuration
  (workspace-scoped; coordinates with Reports/Analytics for cross-module views).
- **Interview** *(aggregate root)* — a scheduled AI or human evaluation event on
  an application; state per `STATE_DIAGRAMS.md` §5.
- **Interview Scorecard / Evaluation** — structured human or AI evaluation
  attached to an interview and aggregated on the candidate profile.
- **Offer** *(aggregate root)* — formal hiring proposal on an application; at most
  one active offer per application; state per `STATE_DIAGRAMS.md` §4.
- **Talent Pool Entry** — a saved/passive/past candidate reference within the
  workspace.
- **Template** — reusable JD, pipeline, question set, scorecard, or message
  template.
- **Employee** *(post-hire context)* — workspace-scoped context of the same
  `User`, created on `Hired`; state per `STATE_DIAGRAMS.md` §6.

**Invariants enforced (restating `APPLICATION_FLOW.md` §10 / `DOMAIN_MODEL.md`
§6):** one application per `(User, Job, Workspace)`; references not copies; one
`User` identity (no candidate account); candidate profile is per
`(User, Workspace)`; absolute tenant isolation; deny-by-default authorization;
AI is advisory; stages/pipelines are data.

## 9. Acceptance Criteria (testable checklist)

- [ ] A `Job` follows exactly the `STATE_DIAGRAMS.md` §2 machine
      (Draft/Published/Paused/Closed/Archived); every unlisted transition is
      rejected.
- [ ] The **public job page** is reachable **without login** only while a job is
      `Published`, and a visitor can begin an application from it.
- [ ] Submitting an application creates **exactly one** `Application` binding
      `User` + `Job` + `Workspace`; a repeat submission produces **history**, not
      a duplicate (`APPLICATION_FLOW.md` §10.1).
- [ ] The applicant resolves to a **single `User`** (register or sign-in); no
      second "candidate" identity is ever created.
- [ ] The CV/resume is stored as a **`File` owned by the user** and only
      **referenced** by the application — never copied (`APPLICATION_FLOW.md`
      §10.2).
- [ ] An `Application` follows the `STATE_DIAGRAMS.md` §3 machine; `Rejected` and
      `Withdrawn` are reachable from any non-terminal state; `Hired` is reached
      only by accepting an `Offer`.
- [ ] Stages are **workspace-configurable data**; no code path branches on a
      stage's name.
- [ ] Every stage move, read, and action is **permission-checked server-side**
      (deny-by-default) and **appended to the Activity Timeline**.
- [ ] A `Candidate Profile` shows only data created **within the current
      workspace**; cross-workspace candidate data is never visible (verified by a
      tenant-isolation test across two workspaces).
- [ ] AI capabilities (CV analysis, resume parsing, AI interview, candidate
      summary, hiring recommendation, comparison, JD/question generation) are
      requested **only** through the AI Engine contract; no provider/SDK is called
      from Recruitment.
- [ ] All AI outputs are stored and presented as **advisory**; a user holding the
      appropriate permission can **override** any AI recommendation, and the
      override governs the application's fate (`APPLICATION_FLOW.md` §7).
- [ ] An `Interview` follows `STATE_DIAGRAMS.md` §5; AI interviews support session
      resume while `InProgress`.
- [ ] An `Offer` follows `STATE_DIAGRAMS.md` §4; at most **one active offer** per
      application; accepting transitions the application to `Hired`.
- [ ] Reaching `Hired` creates an `Employee` context for the same `User` per
      `STATE_DIAGRAMS.md` §6 — never a new account.
- [ ] Job/application/interview/offer transitions publish the documented domain
      events; Recruitment has **no compile-time dependency** on Workflow,
      Notifications, or Search.
- [ ] Heavy/AI work (parsing, analysis, AI interview) runs **asynchronously** via
      the queue; the apply request and board interactions stay within the
      performance budget (`PROJECT_CONSTITUTION.md` §11).
- [ ] Every list endpoint (jobs, applications, candidates, board, talent pool) is
      **paginated**; no unbounded queries; no N+1 access.
- [ ] Every persisted recruitment record carries `workspace_id` and a ULID `id`;
      all access is tenant-guarded at the Infrastructure layer.
- [ ] Bulk pipeline actions respect per-row permission checks and tenant
      isolation; partial failures are reported without corrupting state.
- [ ] Pausing/closing/archiving a job never hard-deletes in-flight applications.
- [ ] The module exposes its public behavior **only** through its `Contracts`
      surface and published events; no entity or repository is exposed.

### Related Documents

`MODULES.md` · `ARCHITECTURE.md` · `DOMAIN_MODEL.md` · `APPLICATION_FLOW.md` ·
`STATE_DIAGRAMS.md` · `PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` ·
`AI_ENGINE.md` · `DATABASE_ARCHITECTURE.md` · `FEATURE_SPECIFICATIONS/AI_Engine.md` ·
`FEATURE_SPECIFICATIONS/Workflow_Engine.md` · `FEATURE_SPECIFICATIONS/Reports_Analytics.md`
