# STATE DIAGRAMS — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `DOMAIN_MODEL.md`, `APPLICATION_FLOW.md`.
> This document defines the **canonical state machines**. No business logic here
> — only states and the allowed transitions between them. Recruitment stages are
> the **default**; workspaces may customize their pipelines (see note in §3).

---

## 1. Conventions

- States are PascalCase nouns. Terminal states are marked **(terminal)**.
- A transition not listed is **forbidden**.
- Each state machine is enforced in the Domain layer of its owning module.
- Customizable stages (Pipeline) are data, not code (see `PERMISSION_MODEL.md`
  for who may change them; `WORKFLOW_ENGINE.md`, Phase 12, for automated moves).

## 2. Job

```
        ┌──────────────────────────────────────────────┐
        ▼                                                │
  ┌───────┐  publish   ┌───────────┐  pause   ┌────────┐│
  │ Draft │──────────▶ │ Published │────────▶ │ Paused ││
  └───┬───┘            └─────┬─────┘ ◀──resume└───┬────┘│
      │                      │ close              │close│
      │                      ▼                    │     │
      │                 ┌────────┐                │     │
      │                 │ Closed │◀───────────────┘     │
      │                 └───┬────┘                       │
      │ archive             │ archive                    │
      ▼                     ▼                            │
   ┌──────────┐ ◀───────────┘                            │
   │ Archived │ (terminal; restore → previous state) ────┘
   └──────────┘
```

| From | Event | To |
|---|---|---|
| Draft | publish | Published |
| Draft | archive | Archived |
| Published | pause | Paused |
| Published | close | Closed |
| Paused | resume | Published |
| Paused | close | Closed |
| Closed | archive | Archived |
| Archived | restore | (previous non-terminal) |

A Job's public page is reachable only while **Published**.

## 3. Application

Default system stages (a workspace MAY rename/insert/remove stages; the
lifecycle semantics below are the default template):

```
 Applied ─▶ Screening ─▶ AI Interview ─▶ Human Interview ─▶ Assessment
    │           │             │                │               │
    │           │             │                │               ▼
    │           │             │                │         Reference Check
    │           │             │                │               │
    └───────────┴─────────────┴────────────────┴───────────────┤
                                                                ▼
                                                             Offer
                                                                │ accept
                                                                ▼
                                                             Hired (terminal)

 Any non-terminal ──reject──▶ Rejected (terminal)
 Any non-terminal ──candidate withdraws──▶ Withdrawn (terminal)
```

- **Applied** is the entry state, created when a `User` submits an application
  (the act of "submitting" → state `Applied`).
- Forward movement is the normal flow; recruiters MAY move backward/skip per
  workspace rules. AI/Human interview outcomes are **advisory**
  (`APPLICATION_FLOW.md` §6) — a human decides the transition.
- **Rejected** and **Withdrawn** are reachable from any non-terminal state.
- **Hired** is reached by accepting an Offer and triggers Employee creation
  (see §6).

| From | Event | To |
|---|---|---|
| Applied | screen | Screening |
| Screening | start AI interview | AI Interview |
| AI Interview | advance | Human Interview |
| Human Interview | request assessment | Assessment |
| Assessment | request references | Reference Check |
| (any non-terminal) | extend offer | Offer |
| Offer | accept | Hired (terminal) |
| Offer | decline | Rejected (terminal) |
| (any non-terminal) | reject | Rejected (terminal) |
| (any non-terminal) | withdraw | Withdrawn (terminal) |

## 4. Offer

```
 Draft ─approve▶ Approved ─send▶ Sent ─┬─accept─▶ Accepted (terminal → Hire)
                                       ├─decline▶ Declined (terminal)
                                       ├─expire──▶ Expired  (terminal)
                                       └─revoke──▶ Revoked  (terminal)
```

| From | Event | To |
|---|---|---|
| Draft | approve | Approved |
| Approved | send | Sent |
| Sent | accept | Accepted |
| Sent | decline | Declined |
| Sent | expire | Expired |
| Sent / Approved | revoke | Revoked |

## 5. Interview (AI or Human)

```
 Scheduled ─start▶ InProgress ─finish▶ Completed ─evaluate▶ Evaluated (terminal)
     │                  │
     ├─cancel─▶ Cancelled (terminal)
     └─no show▶ NoShow    (terminal)
```

AI and Human interviews share this machine; AI interviews additionally support
session resume while **InProgress** (see `AI_ENGINE.md`).

## 6. Employee (post-hire context)

```
 Onboarding ─activate▶ Active ─offboard▶ Offboarding ─complete▶ Terminated (terminal)
```

Created when an Application reaches **Hired**. Employee is a workspace-scoped
context of the same `User` (see `USER_MODEL.md`).

## 7. Membership

```
 Invited ─accept▶ Active ─suspend▶ Suspended ─reactivate▶ Active
    │ expire/reject            │ remove
    ▼                          ▼
 Cancelled (terminal)       Removed (terminal)
```

## 8. Invitation

```
 Pending ─accept▶ Accepted (terminal → creates/activates Membership)
    │ ├─reject──▶ Rejected (terminal)
    │ ├─expire──▶ Expired  (terminal)
    │ └─cancel──▶ Cancelled (terminal)
    └─resend (stays Pending, new expiry)
```

## 9. Subscription (per workspace — detail in Phase 14)

```
 Trialing ─convert▶ Active ─payment fails▶ PastDue ─grace ends▶ Suspended
    │ trial ends (no plan)        │ cancel        │ pay            │ pay
    ▼                             ▼               ▼                ▼
 Expired (terminal*)          Cancelled       Active           Active
```
`*` Expired/Cancelled never hard-delete data (see `ARCHIVING_POLICY.md`, Phase 3,
and `SUBSCRIPTION_ENGINE.md`, Phase 14). Suspended workspaces can still log in,
view data, and renew, but cannot perform gated actions.

## 10. Workspace

```
 Active ─archive▶ Archived ─restore▶ Active
   │ soft-delete
   ▼
 Deleted (soft; recoverable)
```
Ownership transfer is an action on **Active** that reassigns the owner Membership
without changing workspace state.

## 11. Workflow Execution (Phase 12 — listed for completeness)

```
 Pending ─start▶ Running ─┬─complete─▶ Completed (terminal)
                          ├─fail──────▶ Failed ─retry▶ Running
                          ├─await─────▶ WaitingApproval ─approve▶ Running
                          └─cancel────▶ Cancelled (terminal)
```

---

### Related Documents
`APPLICATION_FLOW.md` · `DOMAIN_MODEL.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `DATABASE_ARCHITECTURE.md` (Phase 3) ·
`WORKFLOW_ENGINE.md` (Phase 12) · `SUBSCRIPTION_ENGINE.md` (Phase 14)
