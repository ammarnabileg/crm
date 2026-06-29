# DOMAIN MODEL — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Authority:** This document and `PROJECT_CONSTITUTION.md` define the
> **ubiquitous language** of the system. Every other document and every line of
> code MUST use these terms with these meanings.

---

## 1. Purpose

This is the canonical model of *what exists* in HaHireAI and *how the concepts
relate*. It is intentionally implementation-free: no tables, no classes. It is
the dictionary that the database design (`DATABASE_ARCHITECTURE.md`), the modules
(`MODULES.md`), and the screens (`SCREEN_CATALOG.md`) all defer to.

## 2. The Two System-Level Actors

There are **exactly two** actor types at the system level. No others exist.

| Actor | What it is | Can also be a normal user? |
|---|---|---|
| **System Owner** | A member of the platform operating team. Holds **system permissions** over the platform itself. | **Yes** — same account can create/join workspaces, apply to jobs, etc. |
| **User** | Any human using the platform. | — |

> **System Owner is NOT a separate account type.** It is a `User` that has been
> granted system-level permissions. See `USER_MODEL.md`.

**Candidate, Recruiter, HR, Hiring Manager, Owner, Interviewer, Employee are NOT
actor types.** They are **contexts and permission sets** the same `User` holds
*inside a specific workspace*.

## 3. Ubiquitous Language (Glossary)

| Term | Definition |
|---|---|
| **User** | The single human identity in the system. The only human account type. |
| **System Owner** | A `User` holding system-level permissions over the platform. |
| **Workspace** | An independent, isolated tenant space. The boundary of all business data. Not a company, not a role, not a user. |
| **Membership** | The link between a `User` and a `Workspace`, carrying status, roles, and join metadata. |
| **Role** | A *named bundle of permissions* defined inside one workspace. Never hard-coded. |
| **Permission** | A fine-grained capability key (e.g. `job.create`). The atomic unit of authorization. |
| **Job** | A hiring requisition/posting owned by a workspace. Behaves like its own workspace-within-a-workspace. |
| **Application** | The entity binding a `User` + `Job` + `Workspace`. The single source of a candidacy. |
| **Candidate Profile** | A workspace-scoped **view/projection** of a `User`, derived only from that user's interactions with that workspace. Not an account. |
| **Pipeline** | The ordered set of stages an application moves through (Kanban). |
| **Stage** | A single, customizable column/step within a pipeline. |
| **Interview** | A scheduled evaluation event (AI or human) attached to an application. |
| **Offer** | A formal hiring proposal extended on an application. |
| **Employee** | A post-hire context of a `User` inside a workspace. |
| **Talent Pool** | A workspace's collection of saved/passive/past candidates. |
| **Subscription** | A workspace's commercial plan and entitlements. |
| **Plan** | A globally defined commercial tier (features + limits). |
| **AI Engine** | The central intelligence service every recruitment workflow can route through. |
| **AI Session** | One bounded interaction with the AI Engine (e.g. an AI interview run), with prompts, responses, cost, and usage. |
| **Audit Log** | An immutable record of who did what, when, in which workspace, from where. |
| **Module** | An autonomous unit of the modular monolith with a single responsibility. |
| **Enabled Module** | A module turned on for a given workspace (gated by subscription/settings). |

## 4. Aggregates & Entities (conceptual)

Aggregates are consistency boundaries. The **aggregate root** is the entity other
parts of the system reference.

### 4.1 Identity & Access
- **User** *(root, global)* — credentials, profile, system flags.
- **Workspace** *(root, tenant root)* — the isolation boundary; owns settings, branding.
- **Membership** *(root, workspace-scoped)* — User↔Workspace link; holds assigned roles & status.
- **Role** *(root, workspace-scoped)* — a bundle of permission keys.
- **Permission** *(global catalog)* — the static registry of capability keys.
- **Invitation** *(workspace-scoped)* — a pending membership offer.

### 4.2 Recruitment (bounded context)
- **Job** *(root, workspace-scoped)* — requisition + public posting + hiring team.
- **Application** *(root, workspace-scoped)* — User+Job+Workspace candidacy; owns its timeline & documents.
- **Candidate Profile** *(workspace-scoped projection of User)* — notes, tags, ratings, scorecards *for this workspace only*.
- **Pipeline / Stage** *(workspace-scoped)* — stage definitions (per workspace and/or per job).
- **Interview / Interview Session** *(workspace-scoped)* — scheduled evaluations + results.
- **Offer** *(root, workspace-scoped)* — hiring proposal + approval + status.
- **Employee** *(workspace-scoped)* — post-hire context derived from a hired application.

### 4.3 Intelligence
- **AI Provider / AI Model** *(global catalog; workspace-level config/keys)*.
- **AI Session / Prompt / Response / Usage / Cost / Fallback** *(workspace-scoped)*.

### 4.4 Platform & Support
- **Subscription** *(workspace-scoped)* / **Plan** *(global)* / **Invoice** *(workspace-scoped)*.
- **File** *(workspace-scoped, with ownership & visibility)*.
- **Notification** *(per-user, workspace-contextual)*.
- **Audit Log** *(workspace-scoped + system-level)*.
- **Setting** *(workspace-scoped + global)*.
- **Report / Saved View** *(workspace-scoped)*.

## 5. Core Relationships (high level)

```
User 1───* Membership *───1 Workspace
User 1───* Application *───1 Job *───1 Workspace
Workspace 1───* Role ; Role *───* Permission (via assignment)
Membership *───* Role
Application 1───* Interview ; Application 1───0..1 Offer
Application *───1 (Candidate Profile within Workspace) ───1 User
Workspace 1───* Job, File, AuditLog, Notification, Subscription, Setting
Workspace 1───1 (current) Subscription ───* Plan(reference)
```

The authoritative, fully-detailed relationship set lives in
`RELATIONSHIP_MATRIX.md` and `ER_DIAGRAM.md`. This section is the conceptual
summary; if they ever disagree, this Domain Model's intent governs and the others
are corrected.

## 6. Invariants (must always hold)

1. A human has **one** `User`. Never duplicated as "candidate", "recruiter", etc.
2. Every business record (except global catalogs) belongs to **exactly one**
   `Workspace` and is invisible to all others.
3. A **Candidate Profile is per (User, Workspace)** — it never aggregates a
   user's data across workspaces.
4. An **Application uniquely binds** one User to one Job within one Workspace; a
   user applies to a given job at most once (re-application creates history, not
   duplicates).
5. **Roles are data, not code.** The system never branches on a role's name.
6. **System Owner** capability comes from system permissions on a `User`, not
   from a separate account.
7. Candidate/job data is **never copied** into another entity when a reference
   suffices (no derived/duplicated data — see `DATABASE_ARCHITECTURE.md`).

## 7. Contexts a single User can hold

The same `User`, with one login, can simultaneously be:

- **Owner/Admin** of Workspace A (via membership + permissions),
- a **hiring team member** in Workspace B (different permissions),
- a **candidate** who applied to a job in Workspace C (via an Application),
- an **employee** in Workspace D (post-hire context),
- a **System Owner** of the platform (if granted system permissions).

None of these are accounts. They are projections of one identity through
memberships, permissions, applications, and employment.

---

### Related Documents
`PROJECT_CONSTITUTION.md` · `USER_MODEL.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `ENTITY_CATALOG.md` · `RELATIONSHIP_MATRIX.md` ·
`ER_DIAGRAM.md` · `MODULES.md`
