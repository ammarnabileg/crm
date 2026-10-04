# SYSTEM OVERVIEW — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `DOMAIN_MODEL.md`.

---

## 0. About This Document

This is the **orientation map** of HaHireAI: the single place a new engineer,
reviewer, or stakeholder reads first to understand *what the system is*, *who it
serves*, *what it is made of*, and *how it is built*. It is deliberately
high-level. It introduces concepts and points to the authoritative documents; it
does **not** redefine them.

This document **defers** to `PROJECT_CONSTITUTION.md` (the supreme reference) and
`DOMAIN_MODEL.md` (the ubiquitous language). Where this overview summarizes, the
canon governs; if they ever disagree, this file is the one that is corrected.
Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **MAY**) follow
RFC 2119, exactly as in the Constitution.

---

## 1. What HaHireAI Is

HaHireAI is an **AI-native, multi-tenant hiring operations platform** — an
enterprise recruitment SaaS engineered to the standard of products like **Slack,
Notion, GitHub, Linear, and the Stripe Dashboard**. It is a single, coherent
product where companies **source, evaluate, interview, and hire** talent inside
isolated **Workspaces**, with the central **AI Engine** available to power every
step. It is explicitly **not** a job board and **not** a CRUD application.

**The thesis: hiring is an operational discipline, not a sequence of forms.**
Most hiring tools model recruitment as paperwork — a stack of screens to fill in.
HaHireAI models it as an *operational system*: every `Job`, `Application`,
`Candidate Profile`, and `Interview` is part of one intelligent, observable
pipeline where work is permission-governed, evidence-backed, auditable, and
routable through AI. The UI is a **projection** of that system, never its
definition (Constitution §3.1). We design business domains, not pages.

## 2. The Problem It Solves & Who It Is For

Hiring is fragmented across spreadsheets, inboxes, job boards, and disconnected
applicant trackers. Context is lost between stages, decisions are unaccountable,
evaluation is inconsistent, and a single person is forced to maintain *separate
accounts* for every company they hire at, get hired by, or work for. HaHireAI
removes that fragmentation with two ideas working together:

- **One identity, many contexts (Constitution §3.3).** There is exactly **one**
  human account type — `User`. The same login is reused for every context a
  person ever holds. "Candidate", "Recruiter", "HR", "Hiring Manager",
  "Interviewer", and "Employee" are **contexts and permission sets**, never
  account types (see `USER_MODEL.md`, `DOMAIN_MODEL.md` §2).
- **Workspace as the tenant boundary (Constitution §3.5).** All business data is
  isolated per **Workspace**; cross-workspace leakage is a critical security
  defect (see `WORKSPACE_MODEL.md`).

**Who it is for:**

| Audience | What HaHireAI gives them |
|---|---|
| **Companies & HR teams** (3 to 50,000 people) | A workspace to run hiring as an operational discipline: jobs, pipelines, interviews, offers, analytics, AI assistance. |
| **Individuals** | A single identity that follows them whether they are hiring, being hired, or working — no duplicate "candidate" account. |
| **System Owners** (platform operators) | A Platform Context to operate the SaaS itself: tenants, plans, global configuration, diagnostics. |

## 3. High-Level Capabilities

Each capability is delivered by one or more modules. This is the brief tour; the
**canonical module map is `MODULES.md`** (match its names exactly).

| Capability | What it does | Primary module(s) |
|---|---|---|
| **Recruitment OS** | The full hiring operating system: Jobs, Applications, Candidate Profiles, Pipeline, Interviews (AI + human), Offers, Talent Pool, Templates, Hiring Analytics, Activity Timeline. | **Recruitment** (one bounded context — see `MODULES.md` §3) |
| **AI Engine** | The central, multi-provider intelligence layer every workflow can route through (AI Sessions, prompts, usage, cost, fallback). | **AI Engine** (see `AI_ENGINE.md`) |
| **Workspace platform** | The domain-agnostic tenant platform: identity, isolation, members, roles, settings, branding. | **Workspaces**, **Memberships**, **Users**, **Permissions**, **Settings** |
| **Workflow automation** | Triggers, conditions, actions, approvals, scheduler, and background jobs that automate recruitment work. | **Workflow Engine** |
| **Integrations** | The single sanctioned door to the outside world: API gateway/REST, webhooks, connectors, OAuth/SSO/SCIM-ready. | **Integration Platform** |
| **Billing & subscriptions** | Per-workspace plans, entitlements, payments, invoices, feature flags and limits. | **Subscriptions**, **Billing**, **Licensing** |
| **Observability** | Health, metrics, logs, error tracking, monitors, backups, maintenance. | **Observability** |
| **Platform administration** | The Platform Context for System Owners to manage tenants, plans, and global config. | **System Administration** |
| **Shared services** | Files, Notifications, Search, Audit provided once and consumed via contracts. | **Files**, **Notifications**, **Search**, **Audit** |

> HaHireAI's first business capability is Recruitment, but the workspace platform
> is **domain-agnostic** and MAY host additional business modules later
> (`WORKSPACE_MODEL.md` §1).

## 4. System Context Diagram

There are **exactly two** system-level actors: **System Owner** and **User**
(`DOMAIN_MODEL.md` §2). Every external system is reached **only** through the
**Integration Platform** — business modules MUST NOT embed providers or make
external calls directly (`ARCHITECTURE.md` §8, `MODULES.md` §5).

```
                         ┌──────────────────────────────┐
        Platform Context │          System Owner         │  (a User holding system.* )
        ─────────────────┤  manage tenants, plans,       │
                         │  global config, diagnostics   │
                         └───────────────┬───────────────┘
                                         │
                                         ▼
   Workspace Context   ┌─────────────────────────────────────────────────────┐
   ─────────────────▶  │                    HaHireAI                          │
        User           │              (Modular Monolith)                      │
   (hire / be hired /  │                                                      │
    work)              │   Recruitment · AI Engine · Workflow Engine ·        │
                       │   Workspaces/Memberships/Users/Permissions ·         │
                       │   Files/Notifications/Search/Audit/Settings ·        │
                       │   Subscriptions/Billing/Licensing · Observability ·  │
                       │   System Administration                              │
                       │                                                      │
                       │            ┌──────────────────────────┐             │
                       │            │   Integration Platform    │  ◄── single │
                       │            │  (API · Webhooks · SSO ·  │     egress  │
                       │            │   Connectors)             │     door    │
                       │            └─────────────┬────────────┘             │
                       └──────────────────────────┼──────────────────────────┘
                                                  │  (all external traffic)
                ┌──────────────┬──────────────┬───┴────────┬──────────────┬──────────────┐
                ▼              ▼              ▼            ▼              ▼              ▼
          AI providers   Payment        Email/SMTP   Calendars     Meetings      SSO / IdP
          (LLMs)         providers                   (scheduling)  (video)       (OAuth/SCIM)
                         (Stripe/
                          Moyasar)
```

## 5. Key Concepts at a Glance

The **authoritative glossary is `DOMAIN_MODEL.md` §3** (the ubiquitous language).
This is a pointer, not a substitute — the canon defines these precisely.

| Term | One-line sense (see `DOMAIN_MODEL.md` for the binding definition) |
|---|---|
| **User** | The single human identity; the only human account type. |
| **System Owner** | A `User` holding system-level (`system.*`) permissions over the platform. |
| **Workspace** | The isolated tenant boundary of all business data. |
| **Membership** | The link binding a `User` to a `Workspace` (status, roles, metadata). |
| **Role** | A named bundle of permissions, defined inside one workspace; data, never code. |
| **Permission** | A fine-grained capability key (`resource.action`); the atom of authorization. |
| **Job / Application** | A requisition, and the `User`+`Job`+`Workspace` candidacy bound to it. |
| **Candidate Profile** | A workspace-scoped *projection* of a `User` — never a separate account. |
| **AI Engine / AI Session** | The central intelligence service, and one bounded interaction with it. |

> Do not duplicate or re-version the glossary here. Add or change terms in
> `DOMAIN_MODEL.md` only.

## 6. Platform vs Workspace Contexts

HaHireAI presents two operating contexts, each with its own navigation, generated
dynamically from the current context, permissions, subscription, and enabled
modules (`WORKSPACE_MODEL.md` §7 — there is exactly **one** sidebar, never one
per role).

| | **Platform Context** | **Workspace Context** |
|---|---|---|
| **Who operates here** | System Owners (`system.*` permissions) | Workspace members, via roles/grants |
| **Scope** | The platform itself | One active workspace at a time |
| **Typical actions** | Manage tenants, plans, global config, diagnostics | Run hiring: jobs, pipeline, interviews, offers, reports |
| **Data visibility** | Platform-level + system audit | Strictly that workspace's data (tenant-isolated) |

The same `User` MAY switch freely between workspaces, and (if granted) into the
Platform Context — all with one login. Workspace permissions in one workspace
**never** affect another (`PERMISSION_MODEL.md` §6).

## 7. How the System Is Built — 16-Phase Delivery

HaHireAI is delivered in **16 phases**. **Documentation precedes code**
(Constitution §3.8, §12): no module is implemented before its specification is
written and approved. Phases 1–6 are documentation; Phases 7–16 are
implementation, built on the Modular Monolith architecture (`ARCHITECTURE.md`).

| Phase | Focus | One-line scope |
|---|---|---|
| **1** | Documentation | Foundational canon: constitution, domain, user, workspace, permission, architecture, modules, this overview. |
| **2** | Documentation | Module specifications, blueprints, and self-review gates (`MODULES.md` §7). |
| **3** | Documentation | Database architecture, entity catalog, relationship matrix, ER diagram. |
| **4** | Documentation | Permission catalog, role builder, access policies, security & audit matrices. |
| **5** | Documentation | UI/UX system: screen catalog, navigation/sidebar model, UI guidelines (AR/EN). |
| **6** | Documentation | Cross-cutting guides: security, coding standard, testing, deployment, observability. |
| **7** | **Core Kernel** | Boot, service container, router/dispatcher, config, env, events, logger, errors, module registry, health. |
| **8** | **Installer / DB / Auth / RBAC** | Database engine, zero-touch installer (first System Owner), Authentication, Users, Permissions, System Administration foundations. |
| **9** | **Workspace platform** | Workspaces, Memberships, Settings, and shared services: Files, Notifications, Search, Audit. |
| **10** | **Recruitment** | The hiring OS as one bounded context: Jobs, Applications, Candidate Profiles, Pipeline, Interviews, Offers, Talent Pool, Templates, Analytics. |
| **11** | **AI Engine** | The central, multi-provider AI capability layer that every recruitment workflow can route through. |
| **12** | **Workflow Engine** | Automation: triggers, conditions, actions, approvals, scheduler, background jobs/queue. |
| **13** | **Integration Platform** | API gateway/REST, webhooks, event-bus exposure, connectors, OAuth/SSO/SCIM-ready, developer portal. |
| **14** | **Billing / Subscriptions** | Subscriptions, Billing (payments/invoices), Licensing (feature flags, tenant limits, entitlements). |
| **15** | **Observability** | Health, metrics, logs, error tracking, monitors, backups, maintenance. |
| **16** | **Release certification** | Final hardening, security/performance gates, release certification per the Release Policy (Constitution §14). |

> Phase numbers and module names in this table are bound to `MODULES.md` and
> `ARCHITECTURE.md`. Module documents that reference a phase (e.g. "Phase 11")
> MUST stay consistent with this table.

## 8. Non-Goals / Out of Scope (Initial Release)

To protect the architecture and the schedule, the initial release explicitly does
**not** include the following. These are deliberate non-goals, not omissions.

- **Social login / third-party sign-in.** Out of scope for the initial release
  (`USER_MODEL.md` §6). SSO/OAuth/SCIM arrives later via the **Integration
  Platform** (Phase 13), not as account types.
- **Native mobile apps.** The product is delivered as a responsive web
  application (server-rendered, TailwindCSS, Alpine.js for UI only). No iOS/Android
  native clients in the initial release.
- **Microservices / distributed architecture.** The system **MUST** ship as a
  **Modular Monolith** (Constitution §4, `ARCHITECTURE.md` §1). A hot module MAY
  later be extracted behind its existing contract — but not now.
- **Additional account types.** No `Candidate`, `Recruiter`, `HR`, or `Employee`
  accounts will ever exist; these remain contexts of the single `User`
  (`DOMAIN_MODEL.md` §2, §6). This is a permanent non-goal, not just a phasing one.
- **Hard-coded or reserved roles.** The product ships **zero** default roles;
  authorization is permission-based and roles are data (`PERMISSION_MODEL.md` §3).
- **A PHP framework.** Native PHP 8.3+ only; Laravel/Symfony/etc. are forbidden
  (Constitution §5). Composer is used for dependencies and dev tooling only.
- **Non-recruitment business domains.** The workspace platform is built to be
  domain-agnostic, but only the **Recruitment** domain ships initially
  (`WORKSPACE_MODEL.md` §1).

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `DOMAIN_MODEL.md` · `USER_MODEL.md` ·
`WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`SYSTEM_BLUEPRINT.md` · `AI_ENGINE.md` · `DATABASE_ARCHITECTURE.md` ·
`SECURITY_GUIDE.md` · `UI_GUIDELINES.md`
