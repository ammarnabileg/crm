# USER JOURNEYS — HaHireAI

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `APPLICATION_FLOW.md`, `STATE_DIAGRAMS.md`, `DOMAIN_MODEL.md`.

---

## 0. About This Document

This document maps the **end-to-end user journeys** of HaHireAI — the paths a
real person walks through the product. It documents **flows**, not screens: each
journey is a numbered step sequence naming the **permission** and/or **state**
touched at every step. The UI is a projection of these flows, never their
definition (`SYSTEM_BLUEPRINT.md` §2); for screens see Phase 5's `SCREEN_CATALOG.md`.

This is a **companion deliverable** to `SYSTEM_BLUEPRINT.md` (§0 listing). It
**defers** to `APPLICATION_FLOW.md` (the recruitment lifecycle),
`STATE_DIAGRAMS.md` (the canonical state machines), and `DOMAIN_MODEL.md` (the
ubiquitous language). Where this file summarizes a transition, `STATE_DIAGRAMS.md`
governs; if they disagree, that canon wins and this file is corrected.
Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **MAY**) follow
RFC 2119, as in `PROJECT_CONSTITUTION.md`.

**Three rules govern every journey below** (`DOMAIN_MODEL.md` §6,
`APPLICATION_FLOW.md` §2):

- **One identity, many contexts.** A human has exactly one `User`. "Candidate",
  "Recruiter", "Employee", "System Owner" are **contexts and permission sets** —
  never accounts.
- **Workspace is the tenant boundary.** All business data is workspace-scoped;
  subscription, members, roles, and settings are per workspace.
- **Permissions, not roles.** Every step is gated by a permission key
  (`resource.action`), never by a role name. AI is **advisory**; a permitted
  human always decides.

**Legend.** `──▶` sequence/transition · `[perm: key]` permission checked ·
`{State}` entity state from `STATE_DIAGRAMS.md` · `(event)` domain event emitted.

---

## 1. Visitor → Customer (Workspace Owner)

The flagship acquisition journey: an anonymous visitor becomes the owner of a
paying workspace running live hiring.

- **Actor:** A `User` (created during this journey), becoming a workspace owner.
- **Preconditions:** None. The visitor is anonymous and holds no `Membership`.
- **Outcome:** A `User` owns an `Active` (or `Trialing`) `Workspace`, has invited
  members, built roles, published a `Job`, and carried a candidacy to **Hired**.

**Steps:**

```
1. Visit landing / public site (no login).
2. Register ──▶ create the single `User`. [public] {User: Registered}
3. (Optional) Verify email ──▶ {User: Active}. Verification is configurable
   (USER_MODEL.md §5).
4. First login ──▶ user has NO workspace; sees EXACTLY two paths —
   Create Workspace / Join Workspace; nothing else in nav (USER_MODEL.md §5).
5. Create Workspace. [perm: workspace.create] {Workspace: Active}
   Also creates the owner {Membership: Active}, default Settings, and a full set
   of workspace permissions granted DIRECTLY to the owner — NOT a reserved
   "Owner" role; ZERO default roles (WORKSPACE_MODEL.md §6, PERMISSION_MODEL.md §4).
6. Subscribe / start Trial. [perm: billing.manage]
   {Subscription: Trialing} ──convert──▶ {Subscription: Active}. Per-workspace
   (WORKSPACE_MODEL.md §8; see Journey 7).
7. Invite Members ──▶ Invitation(s) via email / link / code. [perm: member.invite]
   {Invitation: Pending} (event) member.invited. See Journey 3.
8. Create Role + assign permissions. [perm: role.create] then [perm: role.update]
   A Role is a named bundle of permission keys, scoped to THIS workspace, and is
   DATA — no code branches on its name (PERMISSION_MODEL.md §1–§3). Assign to a
   Membership.
9. Publish Job. Create {Job: Draft} [perm: job.create]; publish {Job: Published}
   [perm: job.publish]. (event) recruitment.job.published → Workflow / Notifications
   / Search. Exposes a PUBLIC page served WITHOUT login (APPLICATION_FLOW.md §3).
   MAY use AI capability "Generate JD" via the AI Engine (advisory).
10. Receive Applications. Each applicant creates one `Application` {User+Job+
    Workspace}: {Application: Applied} (event) application.submitted. View list
    [perm: application.view]. (See Journeys 2 and 6.)
11. AI Interview (advisory). [perm: interview.ai.run]
    {Interview: Scheduled ──▶ InProgress ──▶ Completed ──▶ Evaluated}. Runs via the
    AI Engine as capability "Run Interview"; Recruitment NEVER calls a provider
    directly (APPLICATION_FLOW.md §7–§8). Output is a RECOMMENDATION.
12. Pipeline ──▶ move the Application through Stages. [perm: application.move]
    (per move; deny-by-default) {Application: Screening ──▶ … ──▶ Shortlisted}.
    Each move appends to the Activity Timeline (event) application.stage_changed.
13. Offer. {Offer: Draft ──▶ Approved ──▶ Sent} [perm: offer.create / offer.approve
    / offer.send]; {Application: Offer}.
14. Hire. Candidate accepts ──▶ {Offer: Accepted} ──▶ {Application: Hired}
    (event) offer.accepted → onboarding. The `User` gains an EMPLOYEE context
    {Employee: Onboarding} in THIS workspace — not a new account
    (APPLICATION_FLOW.md §9).
```

**Outcome:** The visitor is now a customer: owner of a subscribed workspace with
members, roles, a live pipeline, and a completed hire — all under one `User`.

---

## 2. Candidate (Same User Model)

The other side of Journey 1, from the applicant's perspective. The candidate is
**the same `User` model** as every other actor — applying never mints a second
account (`USER_MODEL.md` §1, `APPLICATION_FLOW.md` §4).

- **Actor:** A `User` (the candidate context inside a target workspace).
- **Preconditions:** A `Job` is `{Published}` in the target workspace.
- **Outcome:** The `User` holds an `Application` that reaches **Hired**, then an
  `Employee` context in that workspace.

**Steps:**

```
1. Discover a public job page (NO login). Reachable only while {Job: Published}
   (STATE_DIAGRAMS.md §2).
2. Click Apply ──▶ system ensures ONE `User` exists: Register OR Log in — never a
   "candidate account" (APPLICATION_FLOW.md §4). {User: Registered/Active}
3. Submit application. [perm: application.create — applicant on own candidacy;
   deny-by-default still applies] Create exactly ONE `Application` {user_id,
   job_id, workspace_id}: {Application: Applied} (event) application.submitted.
     ├─ open Activity Timeline (append-only)
     ├─ attach CV/resume = a `File` OWNED BY THE USER, referenced (never copied,
     │  reusable across applications)
     └─ derive/refresh the workspace-scoped Candidate Profile (a VIEW)
   Re-applying to the same job adds HISTORY, never a duplicate (§8; APPLICATION_FLOW.md §4).
4. AI and/or Human Interview. AI: {Interview: Scheduled ──▶ … ──▶ Evaluated} via
   the AI Engine; Human: interviewer records a scorecard/rating. AI output is
   advisory (APPLICATION_FLOW.md §7).
5. Receive Offer ──▶ {Offer: Sent}, {Application: Offer}.
6. Accept Offer ──▶ {Offer: Accepted} ──▶ {Application: Hired (terminal)}.
7. Employment begins. The same `User` gains an EMPLOYEE context in this workspace
   {Employee: Onboarding ──activate──▶ Active}. Workspace-scoped: the user may
   simultaneously be a candidate in another workspace with zero cross-visibility
   (DOMAIN_MODEL.md §7).
```

**Outcome:** One `User`, now carrying a candidate-turned-employee context in this
workspace, while remaining a single global identity everywhere else.

---

## 3. Invited Member

How a person joins an existing workspace and gains exactly the navigation their
granted permissions allow.

- **Actor:** A `User` (existing or newly registering) invited to a workspace.
- **Preconditions:** A member with `member.invite` issued an `Invitation`
  → `{Invitation: Pending}`.
- **Outcome:** The `User` holds an `{Membership: Active}` and sees a sidebar
  generated from their effective permissions.

**Steps:**

```
1. Receive invite ──▶ via email, shareable link, or join code.
   {Invitation: Pending} (issued in Journey 1, step 7).
2. Open the invite ──▶ if not authenticated, Register or Log in. Still ONE `User`
   — joining never duplicates a person.
3. Accept the invitation. [authenticated identity must match the invite]
   {Invitation: Accepted (terminal)} (STATE_DIAGRAMS.md §8) ──▶ creates/activates
   {Membership: Active} (§7). (Else: reject → {Invitation: Rejected}; lapse →
   {Invitation: Expired}.)
4. Join the workspace ──▶ it now appears in the user's workspace switcher.
5. See navigation PER ASSIGNED PERMISSIONS. Effective permissions = union of all
   granted Roles' permissions (+ any direct grants) (PERMISSION_MODEL.md §4).
   Exactly ONE sidebar, generated dynamically from active context + permissions +
   subscription + enabled modules (WORKSPACE_MODEL.md §7, SYSTEM_BLUEPRINT.md §8)
   — never one sidebar per role. Items the member lacks permission for are absent;
   hiding UI is NEVER a substitute for server-side checks (PERMISSION_MODEL.md §5).
```

**Outcome:** The member operates inside one workspace with precisely the
capabilities granted — and nothing else.

---

## 4. System Owner

A System Owner operates the platform **and** uses it as an ordinary user, all on
one account. `System Owner` is a capability (`system.*` permissions on a `User`),
**not** a separate account type (`USER_MODEL.md` §2, `DOMAIN_MODEL.md` §2).

- **Actor:** A `User` holding `system.*` permissions.
- **Preconditions:** The user was granted system permissions (the first System
  Owner is created by the installer; see `INSTALLATION.md`).
- **Outcome:** Platform-level operations performed; the same account also used as
  an ordinary workspace user.

**Steps:**

```
1. Log in ──▶ single `User` login (same credentials as any user).
2. Enter the Platform Context. Available ONLY because the user holds `system.*`
   permissions (SYSTEM_BLUEPRINT.md §8); offered alongside the user's workspaces.
3. Operate the platform (each action deny-by-default, system-scoped):
     ├─ Manage workspaces (tenants)   [perm: system.workspaces.manage]
     ├─ Manage users                  [perm: system.users.manage]
     ├─ Manage subscriptions / plans  [perm: system.subscriptions.manage]
     ├─ Manage AI providers (global)  [perm: system.ai.manage]
     ├─ Run diagnostics               [perm: system.diagnostics.run]
     └─ View system audit             [perm: system.audit.view]
   Platform Context data is platform-level + system audit; it does NOT pierce
   tenant isolation of workspaces' business data (SYSTEM_OVERVIEW.md §6).
4. ALSO use the platform as an ordinary User (same account):
     ├─ Create own Workspace ──▶ Journey 1 (workspace owner)
     ├─ Apply to a Job       ──▶ Journey 2 (candidate context)
     └─ Join a workspace     ──▶ Journey 3 (invited member)
   Workspace permissions are independent of `system.*`; system permissions grant
   NO authority inside any workspace's business data (USER_MODEL.md §2,
   PERMISSION_MODEL.md §6).
```

**Outcome:** One identity operates the SaaS in the Platform Context and consumes
it in the Workspace Context, with strictly separated authorization.

---

## 5. Multi-Workspace User

A single `User` belongs to several workspaces with **different permissions in
each**, switching contexts freely (`DOMAIN_MODEL.md` §7, `USER_MODEL.md` §4,
`WORKSPACE_MODEL.md` §7).

- **Actor:** A `User` with `{Membership: Active}` in two or more workspaces.
- **Preconditions:** The user joined/created multiple workspaces (Journeys 1, 3).
- **Outcome:** The user works in one active workspace at a time, with the correct
  permissions and tenant-isolated data for that workspace.

**Steps:**

```
1. Log in ──▶ one `User`, one set of credentials.
2. View workspaces ──▶ the switcher lists every {Membership: Active}, e.g.
   Owner/Admin of Workspace A; hiring-team member in B; candidate (via
   Application) in C; Employee in D (DOMAIN_MODEL.md §7).
3. Select a workspace ──▶ sets the ACTIVE Workspace Context — one active
   workspace at a time (SYSTEM_OVERVIEW.md §6).
4. Navigation + data recompute for the active workspace: sidebar = active context
   + THIS workspace's effective permissions + its subscription + its enabled
   modules. Permissions in Workspace A NEVER affect B (PERMISSION_MODEL.md §6).
5. Switch workspace ──▶ repeat from step 3. Data is strictly tenant-isolated:
   nothing from one workspace is visible in another, including the existence of
   the user's candidacies elsewhere (WORKSPACE_MODEL.md §3, §8).
6. (If granted) Switch to Platform Context ──▶ Journey 4. Offered only when the
   user holds `system.*` permissions.
```

**Outcome:** The same identity fluidly changes context; authorization and
visibility are always scoped to the active workspace (or the Platform Context).

---

## 6. Recruiter Daily Loop

The recurring operational loop of a hiring-team member working a pipeline. AI
advises at each step; the **human with the right permission decides**
(`APPLICATION_FLOW.md` §5–§7).

- **Actor:** A `User` whose `Membership` holds recruitment permissions in the
  active workspace.
- **Preconditions:** `{Workspace: Active}`, a non-suspended `Subscription`, at
  least one `{Job: Published}` with inbound `Application`s.
- **Outcome:** Applications progress; interviews are scheduled; the candidacy
  reaches a permitted human hiring decision.

**Steps:**

```
1. Review new applications. [perm: application.view] Inbound {Application: Applied};
   AI may have produced advisory "Resume Parsing" / "CV analysis" via the AI
   Engine (APPLICATION_FLOW.md §8.1).
2. Move pipeline stages. [perm: application.move] (per move; deny-by-default)
   {Application: Applied ──▶ Screening ──▶ … } (STATE_DIAGRAMS.md §3). Recruiters
   MAY move backward/skip per workspace rules. Each move ──▶ Activity Timeline +
   (event) application.stage_changed.
3. Schedule interviews. Human: [perm: interview.schedule] {Interview: Scheduled};
   AI: [perm: interview.ai.run] via the AI Engine. {Interview: Scheduled ──▶
   InProgress ──▶ Completed ──▶ Evaluated}.
4. Add notes / tags. [perm: candidate.note] / [perm: candidate.tag] Recorded on
   the workspace-scoped Candidate Profile (a VIEW) — NEVER on the global `User`,
   NEVER visible to other workspaces (APPLICATION_FLOW.md §6).
5. Make a hiring decision (AI advisory, human decides). AI MAY produce "Hiring
   Recommendation" / "Candidate Comparison" (advisory). The permitted human picks:
     ├─ Advance ──▶ next stage / extend Offer        [perm: offer.create]
     ├─ Reject  ──▶ {Application: Rejected (terminal)} [perm: application.reject]
     └─ Hold    ──▶ stay in stage
   The HUMAN decision ALWAYS overrides any AI result (APPLICATION_FLOW.md §7).
6. Repeat ──▶ the loop continues across the pipeline / other jobs.
```

**Outcome:** A steadily advancing, auditable pipeline where every transition is
permission-checked, timeline-logged, and human-authored despite AI assistance.

---

## 7. Subscription Lifecycle (Brief)

The commercial journey of a workspace's `Subscription`. Per-workspace; full
detail is **Phase 14** (`SUBSCRIPTION_ENGINE.md`); states per `STATE_DIAGRAMS.md`
§9.

- **Actor:** A `User` with `billing.manage` in the workspace (plus the System
  Owner via `system.subscriptions.manage` for platform oversight).
- **Preconditions:** A `{Workspace: Active}`.
- **Outcome:** The workspace moves through its commercial states; gated actions
  follow entitlement; data is never hard-deleted.

**Steps:**

```
1. Start trial ──▶ {Subscription: Trialing}. [perm: billing.manage]
2. Convert (add a plan / pay) ──▶ {Subscription: Active}. Enabled modules + limits
   derive from the Plan (WORKSPACE_MODEL.md §8).
3. Payment fails ──▶ {Subscription: PastDue}. A grace period applies.
4. Grace ends ──▶ {Subscription: Suspended}. Suspended workspaces can STILL log
   in, view data, and renew, but CANNOT perform gated actions (STATE_DIAGRAMS.md
   §9); gated actions disappear from nav while remaining server-enforced.
5. Renew (pay) ──▶ {Subscription: Active} (from PastDue or Suspended).
   Alternatively: trial ends with no plan ──▶ {Expired}; cancel ──▶ {Cancelled}.
   Expired/Cancelled NEVER hard-delete data (ARCHIVING_POLICY.md, Phase 3).
```

**Outcome:** Billing status governs which capabilities are available, without ever
destroying the workspace's data. See Phase 14 for proration, invoices, dunning.

---

## 8. Invariants & Notes (binding across all journeys)

These restate, for user journeys, the binding rules of `DOMAIN_MODEL.md` §6,
`USER_MODEL.md` §7, `WORKSPACE_MODEL.md` §8, `PERMISSION_MODEL.md` §8, and
`APPLICATION_FLOW.md` §10. If a journey above appears to contradict one of these,
the invariant governs and the journey text is corrected.

1. **One identity.** A human has exactly one `User`. No journey ever creates a
   second account for a "candidate", "recruiter", "employee", or "System Owner";
   those are contexts and permission sets on the one identity
   (`USER_MODEL.md` §1, `DOMAIN_MODEL.md` §2).
2. **A new user with no workspace sees only two paths:** *Create Workspace* or
   *Join Workspace* — nothing else (`USER_MODEL.md` §5).
3. **Workspace is the tenant boundary.** Every business record carries a
   `workspace_id` and is invisible to all other workspaces. Cross-workspace
   leakage (including the mere existence of a candidacy elsewhere) is a
   **critical security defect** (`WORKSPACE_MODEL.md` §3, §8).
4. **Subscription is per workspace.** Plans, limits, and enabled modules belong
   to the workspace, not the user (`WORKSPACE_MODEL.md` §8; Journey 7).
5. **Permissions, not roles.** Every step is gated by a permission key; no
   journey, UI element, or code path branches on a role's name. Roles are data
   created by customers; the product ships **zero** default roles
   (`PERMISSION_MODEL.md` §1–§3).
6. **Deny-by-default.** Every state-changing and data-reading step is
   permission-checked server-side; hiding a nav item is never a substitute
   (`PERMISSION_MODEL.md` §5).
7. **The owner is granted permissions directly**, at workspace creation — not via
   a reserved "Owner" role (`PERMISSION_MODEL.md` §4, `WORKSPACE_MODEL.md` §6).
8. **One `Application` per (User, Job, Workspace).** Re-applying produces history,
   never a duplicate (`APPLICATION_FLOW.md` §4, §10).
9. **References, not copies.** The CV/resume is a `File` owned by the `User` and
   referenced by the application; identity data is read from the `User`, never
   duplicated (`APPLICATION_FLOW.md` §10, Invariant 2).
10. **The Candidate Profile is a per-(User, Workspace) VIEW**, never an account
    and never aggregated across workspaces (`APPLICATION_FLOW.md` §6).
11. **AI is advisory; humans decide.** Every AI output is a recommendation
    routed through the AI Engine as a capability; a permitted human override —
    not the AI — determines the outcome (`APPLICATION_FLOW.md` §7, `AI_ENGINE.md`
    §8.1).
12. **Stages and pipelines are data.** No journey depends on a stage's *name*;
    workspaces customize pipelines without engine changes (`APPLICATION_FLOW.md`
    §5, `STATE_DIAGRAMS.md` §3).
13. **One product, one sidebar.** Navigation is generated dynamically from the
    active context + permissions + subscription + enabled modules; there is never
    one sidebar per role (`WORKSPACE_MODEL.md` §7, `SYSTEM_BLUEPRINT.md` §8).

> **Note on permission keys.** Keys shown above (e.g. `job.publish`,
> `application.move`, `interview.ai.run`, `member.invite`, `billing.manage`,
> `system.workspaces.manage`) follow the `resource.action` grammar of
> `PERMISSION_MODEL.md` §2. The authoritative registry is the **Permission
> Catalog** (`PERMISSION_CATALOG.md`, Phase 4); where a key here differs from the
> catalog, the catalog governs.

---

### Related Documents

`APPLICATION_FLOW.md` · `STATE_DIAGRAMS.md` · `DOMAIN_MODEL.md` ·
`USER_MODEL.md` · `WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` ·
`SYSTEM_BLUEPRINT.md` · `SYSTEM_OVERVIEW.md` · `AI_ENGINE.md` ·
`NAVIGATION_MAP.md` (Phase 2) · `PERMISSION_CATALOG.md` (Phase 4) ·
`SUBSCRIPTION_ENGINE.md` (Phase 14)
