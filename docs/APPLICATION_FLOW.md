# APPLICATION FLOW — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `DOMAIN_MODEL.md`. **State machines:** `STATE_DIAGRAMS.md` (Phase 2).

---

## 1. Purpose & Scope

This document describes the **end-to-end recruitment flow** of HaHireAI
*conceptually* — the lifecycle of a hire from a published `Job` to an `Employee`
context. It is deliberately implementation-free: **no UI, no controllers, no
SQL, no class names**. It explains *what happens and why*, and how the core
entities relate as work moves through the system.

It defers to `DOMAIN_MODEL.md` for the ubiquitous language and to `MODULES.md`
for module boundaries. All recruitment behavior described here lives inside the
single **Recruitment** module (its sub-domains: `Jobs`, `Applications`,
`Candidate Profiles`, `Pipeline`, `Interviews`, `Offers`, `Talent Pool`,
`Templates`, `Activity Timeline`, `Automation Hooks`). Formal state machines and
transition guards are deferred to `STATE_DIAGRAMS.md` (Phase 2).

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119, as
established in `PROJECT_CONSTITUTION.md`.

---

## 2. Lifecycle Overview (narrative)

Hiring in HaHireAI is **one continuous, observable operation**, not a sequence of
disconnected forms. A workspace publishes a job; a person — always a single
`User` identity — applies; that act of applying creates an **`Application`**, the
durable record of the candidacy. The application then travels through a
workspace-defined **`Pipeline`** of **`Stage`s**, accumulating interviews,
evaluations, documents, and AI-generated insight along the way. If the journey
ends in success, an **`Offer`** is extended and accepted, and the user gains an
**`Employee`** context inside that workspace.

Three ideas govern everything below:

1. **The `Application` is the spine.** It binds `User` + `Job` + `Workspace` and
   is the single source of truth for one candidacy. Everything (timeline,
   documents, interviews, evaluations, AI runs, offer) hangs off it.
2. **The `Candidate Profile` is a *view*, not an account.** It is a
   workspace-scoped projection of the `User`, assembled only from that user's
   interactions with *that* workspace. It is never an account and never spans
   workspaces.
3. **AI advises; humans decide.** Every AI contribution is a *recommendation*
   produced through the central **AI Engine**. A human with the right permission
   can always override it.

```
   ┌────────┐  publishes  ┌────────┐  applies  ┌───────────────┐
   │Workspace│──────────▶ │  Job   │◀──────────│     User      │
   └────────┘             └────────┘           └───────┬───────┘
        ▲                      ▲                        │
        │  isolation boundary  │                        │ creates
        │                      └──────────┐             ▼
        │                                 │      ┌───────────────┐
        └──────── scopes everything ──────┴────▶ │  Application  │ ◀── the spine
                                                 └───────┬───────┘
                                                         │ projects
                                                         ▼
                                              ┌────────────────────┐
                                              │ Candidate Profile  │ (workspace view)
                                              └────────────────────┘
```

---

## 3. Job Lifecycle

A **`Job`** is a hiring requisition owned by exactly one `Workspace`; the Domain
Model describes it as "a workspace-within-a-workspace" because it carries its own
hiring team, pipeline, and applications. Its states:

```
        create               publish              pause
  ┌────┐ ───────▶ ┌───────┐ ─────────▶ ┌───────────┐ ─────▶ ┌────────┐
  │ ·  │          │ Draft │            │ Published │        │ Paused │
  └────┘          └───┬───┘ ◀───────── └─────┬─────┘ ◀───── └───┬────┘
                      │      unpublish        │     resume        │
                      │                       │ close             │ close
                      │                       ▼                   ▼
                      │                 ┌──────────┐         ┌──────────┐
                      └────────────────▶│  Closed  │────────▶│  Closed  │
                       discard/close    └────┬─────┘         └──────────┘
                                             │ archive
                                             ▼
                                       ┌──────────┐
                                       │ Archived │  (read-only, retained)
                                       └──────────┘
```

| State | Meaning | Public job page? | Accepts applications? |
|---|---|---|---|
| **Draft** | Being prepared; not visible externally. | No | No |
| **Published** | Live and discoverable. | **Yes** | **Yes** |
| **Paused** | Temporarily hidden; existing applications continue. | No | No |
| **Closed** | Hiring concluded or abandoned; pipeline frozen. | No | No |
| **Archived** | Retained for history/analytics; read-only. | No | No |

- The **public job page** is served **without login**. A visitor MUST be able to
  read a `Published` job and begin an application without first holding a
  `Membership` in the workspace (account creation/authentication happens as part
  of applying — see §4).
- Stage definitions, screening questions, and required documents are configured
  per job/workspace from `Templates` and `Pipeline` settings.
- Pausing/closing a job MUST NOT delete in-flight applications; it changes only
  whether *new* candidacies can begin. Archiving never hard-deletes data (see
  `ARCHIVING_POLICY.md`).
- Job transitions emit domain events (e.g. `recruitment.job.published`,
  `recruitment.job.closed`) that the **Workflow Engine**, **Notifications**, and
  **Search** subscribe to. The Recruitment module does **not** depend on those
  modules (see `MODULES.md` §5).

---

## 4. Candidacy Creation (the `Application`)

When a `User` applies to a `Published` job, the system creates **exactly one**
`Application` binding **`User` + `Job` + `Workspace`**. This is the moment a
candidacy comes into existence.

```
  Visitor reads public job page (no login)
        │
        ▼
  Applies ──▶ ensure a single `User` identity exists
        │        (register or sign in — never a "candidate account")
        ▼
  Create `Application` { user_id, job_id, workspace_id }
        │
        ├─▶ open Activity Timeline (append-only)
        ├─▶ set initial status = Submitted
        ├─▶ attach Documents (CV/resume = File OWNED BY THE USER, referenced)
        ├─▶ derive/refresh the workspace-scoped Candidate Profile (a VIEW)
        └─▶ enqueue AI capabilities (e.g. "Resume Parsing", "Analyze CV")
                via the AI Engine — asynchronously
```

Rules:

- The applying human is, and remains, **one `User`** (see `USER_MODEL.md`). There
  is **no** "candidate account." Applying may require registering or signing in,
  but it never creates a second identity.
- The CV/resume is a **`File` owned by the user**, *referenced* by the
  application — **never copied** into it. The same file is reusable across
  applications (Invariant, §9).
- The `Application` **owns** its own timeline, status, document references, and AI
  work queue. It does **not** duplicate user profile data; person-level data is
  read from the `User`, workspace-specific candidacy data lives on the
  application and the `Candidate Profile` view.
- A user applies to a given job **at most once**. A repeat application creates
  **history on the existing candidacy**, not a duplicate `Application` (Invariant
  §9; re-application semantics deferred to `STATE_DIAGRAMS.md`).
- Heavy or AI work (parsing, analysis) MUST run **asynchronously** via the queue
  so the apply request stays responsive (`PROJECT_CONSTITUTION.md` §11).

---

## 5. Pipeline Progression

An `Application` advances through an ordered **`Pipeline`** of **`Stage`s**. The
canonical (default) flow:

```
  Submitted
     │
     ▼
  Screening ───────────────┐
     │                     │
     ▼                     │
  AI Interview             │
     │                     ├──▶ Rejected   (terminal, by workspace decision)
     ▼                     │
  Human Interview          │
     │                     ├──▶ Withdrawn   (terminal, by the applicant)
     ▼                     │
  (Assessment)  ← optional │
     │                     │
     ▼                     │
  Shortlisted ─────────────┘
     │
     ▼
   Offer
     │
     ▼
   Hired   (terminal, success → Employee context, see §7)
```

- **Stages are customizable per workspace** (and MAY differ per job). The
  sequence above is the default template; a workspace MAY add, rename, remove, or
  reorder stages via `Pipeline`/`Templates`. No code path branches on a stage's
  *name* — stages are **data**, mirroring the "roles are data" rule of
  `PERMISSION_MODEL.md`.
- **`Rejected`** and **`Withdrawn`** are reachable from (almost) any active
  stage: rejection is a workspace decision; withdrawal is the applicant's. Both
  are terminal for the active flow but preserved in history.
- Every stage change is **permission-checked** (deny-by-default;
  `PERMISSION_MODEL.md` §5) and **appended to the Activity Timeline** — the
  application's immutable, observable history (`Audit` integration).
- A stage transition publishes a domain event
  (e.g. `applications.application.stage_changed`) consumed by the **Workflow
  Engine** (automation hooks, §8) and **Notifications**.
- The `(Assessment)` stage is optional and configured per workspace; some
  pipelines collapse screening + AI interview, others add take-home or panel
  rounds. The flow MUST accommodate this without engine changes.

---

## 6. The Candidate Profile as a Workspace-Scoped View

The **`Candidate Profile`** is one of the most important — and most often
misunderstood — concepts. It is a **per-`(User, Workspace)` projection**, *not* an
account and *not* a global record.

```
                       ┌──────────────────────────────────────────┐
                       │                 User (one)               │
                       │   global identity: name, email, CV file   │
                       └───────────────┬─────────────┬────────────┘
                                       │             │
              projection (Workspace A) │             │ projection (Workspace B)
                                       ▼             ▼
        ┌────────────────────────────────┐   ┌────────────────────────────────┐
        │  Candidate Profile @ Workspace A│   │  Candidate Profile @ Workspace B│
        │  notes, tags, ratings,          │   │  notes, tags, ratings,          │
        │  scorecards, AI insights,       │   │  scorecards, AI insights,       │
        │  applications IN A only         │   │  applications IN B only         │
        └────────────────────────────────┘   └────────────────────────────────┘
                       ▲                                   ▲
                       └──────── NEVER cross this line ────┘
                              (absolute tenant isolation)
```

**What Workspace A can see in its Candidate Profile:**

- The user's applications, interviews, evaluations, documents, notes, tags,
  ratings, scorecards, and AI insights **that were created within Workspace A**.
- Person-level identity data the user has made available (name, contact, the CV
  file the user attached to an application in A).

**What Workspace A MUST NOT see:**

- Anything from Workspace B: B's notes, B's ratings, B's interviews, B's
  applications, the fact that the user even applied elsewhere. There is **no
  cross-workspace aggregation** (Invariant §9; `WORKSPACE_MODEL.md` §3,
  `DOMAIN_MODEL.md` Invariant 3).

Because the profile is a *view*, it carries no independent login and stores no
duplicated identity data. Workspace-specific evaluative data (notes, ratings,
scorecards) belongs to the profile **within that workspace only**. Cross-workspace
leakage of any of this is a **critical security defect**
(`PROJECT_CONSTITUTION.md` §10).

---

## 7. Interviews & Evaluation (recommendations, human override wins)

Interviews are scheduled evaluation events — **AI** or **human** — attached to an
`Application`.

```
  Application reaches an interview stage
        │
        ├──▶ AI Interview ── runs via AI Engine ──▶ produces a RECOMMENDATION
        │        (capability: "Run Interview")     (transcript, scores, summary)
        │
        └──▶ Human Interview ── interviewer(s) record a scorecard / rating
                 │
                 ▼
        Evaluations aggregate on the Candidate Profile (workspace-scoped)
                 │
                 ▼
        AI may produce a "Hiring Recommendation" / "Candidate Comparison"
                 │
                 ▼
        ┌─────────────────────────────────────────────────────────────┐
        │  HUMAN DECISION (permission-gated) — advance / reject / hold  │
        │  The human decision ALWAYS overrides any AI recommendation.   │
        └─────────────────────────────────────────────────────────────┘
```

- All AI evaluation output (scores, summaries, hiring recommendations,
  comparisons, risk/culture-fit signals) is **advisory**. The `AI_ENGINE.md`
  human-in-the-loop principle is binding here: **a recruiter with the appropriate
  permission can override any AI result**, and the override — not the AI output —
  determines the application's fate.
- AI interviews are requested as **capabilities** through the AI Engine
  (`interview.ai.run` permission); the Recruitment module **never** calls an AI
  provider directly (see §8 and `AI_ENGINE.md`).
- Human and AI evaluations are both recorded on the workspace-scoped
  `Candidate Profile`/timeline, never on the global `User`.

---

## 8. Where AI & the Workflow Engine Participate

### 8.1 AI participation (always via the AI Engine)

Every AI touch-point routes through the central **AI Engine** as a *capability
request* — modules request capabilities, never providers (`AI_ENGINE.md`,
`PROJECT_CONSTITUTION.md` §3.2).

```
  Recruitment sub-domain ──requests capability──▶ AI Engine ──▶ (provider abstraction)
       e.g. Applications              "Analyze CV"
            Interviews                "Run Interview"
            Candidate Profiles        "Summarize Candidate", "Hiring Recommendation"
            Jobs / Templates          "Generate JD", "Interview Questions"
```

Typical AI participation across the flow:

| Flow point | Capability requested (via AI Engine) |
|---|---|
| Job creation | JD generation, interview-question generation |
| Application created | Resume parsing, CV analysis |
| Screening | Skills / strengths / weaknesses / risk / culture-fit analysis |
| AI Interview stage | Run interview, score, summarize |
| Shortlisting | Candidate summary, candidate comparison, hiring recommendation |
| Communication | Email generation, translation |

All outputs are recommendations (§7). Cost, usage, and conversation tracking are
the AI Engine's responsibility (`AI_ENGINE.md` §6).

### 8.2 Workflow Engine automation (event-driven hooks)

The **Workflow Engine** automates transitions and side-effects by **subscribing
to recruitment domain events** — it is *not* a dependency of Recruitment
(`MODULES.md` §5: Recruitment publishes, Workflow subscribes).

```
  Recruitment publishes              Workflow Engine reacts (conditions → actions)
  ───────────────────────            ────────────────────────────────────────────
  application.submitted       ──▶    auto-acknowledge, enqueue AI screening
  application.stage_changed   ──▶    notify hiring team, schedule next interview
  interview.completed         ──▶    request AI summary, move stage if rule matches
  offer.extended / accepted   ──▶    trigger onboarding, create Employee context
  job.closed                  ──▶    archive pipeline, notify applicants
```

Automation hooks are **conditional** (rule → action) and **never bypass
permission checks or tenant isolation**. A workflow acts within a single
workspace's data only. Deep specification: `MODULES.md` (Workflow Engine, Phase
12) and the Workflow Engine spec.

---

## 9. Offer → Hire → Employee (post-hire)

```
  Shortlisted ──▶ Offer (extended) ──▶ Offer (approved) ──▶ Offer (accepted)
                       │                     │                    │
                       │ rejected/withdrawn  │ rejected           │ declined
                       ▼                     ▼                    ▼
                   (terminal)            (terminal)           (terminal)
                                                                  │ accepted
                                                                  ▼
                                                          Application = Hired
                                                                  │
                                                                  ▼
                                              `User` gains an EMPLOYEE context
                                                  in this Workspace (post-hire)
```

- An **`Offer`** is a formal hiring proposal on a single application (at most one
  active offer per application; `DOMAIN_MODEL.md` §5). It carries its own
  approval and status.
- Acceptance moves the application to **`Hired`** and creates an **`Employee`**
  context — a **post-hire context of the same `User`** inside that workspace, *not*
  a new account (`DOMAIN_MODEL.md` §4.2, `USER_MODEL.md` §4). Offer/onboarding
  side-effects are typically driven by the Workflow Engine (§8.2).
- The `Employee` context, like every other context, is workspace-scoped: the same
  user may be an employee in one workspace and a candidate in another, with no
  cross-visibility.

---

## 10. Invariants (must always hold)

These restate, for the recruitment flow, the binding rules of `DOMAIN_MODEL.md`
§6, `WORKSPACE_MODEL.md` §8, and `PROJECT_CONSTITUTION.md` §15.

1. **One application per (User, Job, Workspace).** A user applies to a given job
   at most once; re-application produces history, **never** a duplicate.
2. **References, not copies.** Candidate/job/user data is **never** copied into
   another entity when a reference suffices — the CV file is owned by the `User`
   and referenced; the application reads identity from the `User`.
3. **One identity.** The applicant is always a single `User`; there is no
   candidate/recruiter/employee *account* — only contexts.
4. **The Candidate Profile is per (User, Workspace)** and **never** aggregates
   across workspaces.
5. **Absolute tenant isolation.** Every recruitment record carries `workspace_id`
   and is invisible to all other workspaces; cross-workspace leakage is a
   critical defect.
6. **Deny-by-default authorization.** Every transition, read, and action is
   permission-checked server-side (`PERMISSION_MODEL.md`).
7. **AI is advisory.** No AI output is binding; a permitted human decision always
   overrides it.
8. **Stages and pipelines are data.** No logic branches on a stage's name; they
   are workspace-configurable.

---

### Related Documents

`DOMAIN_MODEL.md` · `USER_MODEL.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `ARCHITECTURE.md` · `MODULES.md` · `AI_ENGINE.md` ·
`STATE_DIAGRAMS.md` (Phase 2) · `ARCHIVING_POLICY.md`
