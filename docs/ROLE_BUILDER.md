# ROLE BUILDER — HaHireAI

> **Status:** Adopted (Phase 4) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_MODEL.md`, `PERMISSION_CATALOG.md`.

---

## 1. Purpose & Scope

This document specifies the **Role Builder**: the conceptual capability by which a
workspace composes its own roles from permission keys. It defines *what* a role
is, *who* may shape one, *how* permissions are selected, and *how* role changes
are governed. It describes **conceptual flows only** — no UI layout, no
component, no code. For the meaning of permissions and the enforcement model see
`PERMISSION_MODEL.md`; for the authoritative list of permission keys see
`PERMISSION_CATALOG.md`. Where the two disagree, the catalog and the model
govern.

The Role Builder exists so that **every role a customer needs is built by the
customer**, never by editing code (`PERMISSION_MODEL.md` §8, invariant 6).

---

## 2. What a Role Is

- A **Role** is a **named bundle of permission keys**, scoped to exactly **one
  workspace**. It is ordinary workspace **data** (`PERMISSION_MODEL.md` §3).
- A Role MUST NOT be a code construct. The system **MUST NOT** ship reserved or
  system roles, and **MUST NOT** branch on a role's name anywhere
  (`PERMISSION_MODEL.md` §1).
- The product ships **zero** default roles. A new workspace begins with no roles;
  the owner builds whatever the organization requires.
- A Role carries only: a human-readable **name**, an optional **description**,
  and a **set of permission keys** drawn from `PERMISSION_CATALOG.md`. It carries
  no privileged status, no precedence, and no special-casing.
- Roles in one workspace are invisible to and independent of roles in any other
  workspace. The same person MAY hold entirely different roles per workspace
  (`PERMISSION_MODEL.md` §4).

> **Naming is cosmetic.** A role may be called "Administrator", "Recruiter", or
> "Banana"; the name affects nothing but display. Authorization derives **only**
> from the permission keys the role contains. This is the load-bearing guarantee
> of the whole model — do not weaken it.

---

## 3. Capabilities

A member holding the relevant `role.*` / `permission.*` keys (see
`PERMISSION_CATALOG.md` §2 → *Roles & Permissions*) MAY perform the following.
Each capability is itself permission-gated and audited; none is implied by a role
name.

| Capability | Required permission | Notes |
|---|---|---|
| **View** roles | `role.view` | Prerequisite for every other role operation. |
| **Create** a role | `role.create` | Produces a new empty (or template-seeded) role. |
| **Rename / Update** a role | `role.update` | Edits name, description, and selected permissions. |
| **Clone** a role | `role.clone` | Copies an existing role into a new, fully independent role. |
| **Delete** a role | `role.delete` | Subject to the reassignment rules in §7. |
| **Assign permissions** to a role | `permission.assign` | Add/remove permission keys on a role. |
| **Assign roles** to members | `permission.assign` + `member.update` | Grants/removes a role on a `Membership` (a composite action — see §8 and `ACCESS_POLICIES.md`). |

Implementations **MUST** check the permission **key** for each capability and
**MUST NOT** infer capability from the actor's role name or from any role's
title.

Behavioural notes on the capabilities:

- **Create** yields a new bundle that grants **nothing** until permissions are
  added (deny-by-default, `PERMISSION_MODEL.md` §5). It MAY be seeded from a
  **template** (§5); the result is still an ordinary editable role.
- **Rename** changes display text only and never alters access. **Update** to the
  permission set takes effect for the **union** held by every member assigned
  that role at the next authorization check (`PERMISSION_MODEL.md` §4) —
  broadening a role broadens it for *all* current holders.
- **Clone** copies a role's permission set into a **new, independent** role with
  no link back to its source; it is the recommended way to derive a variant
  (e.g. "Recruiter (read-only)") without disturbing the original.
- **Delete** removes the bundle subject to §7. **Assigning/removing** a role on a
  member changes that member's effective permissions immediately and is a
  membership change governed jointly by Memberships and Permissions (§8).

---

## 4. Permission Selection (Grouped by Catalog Categories)

When building or editing a role, permissions are presented **grouped by the
display categories defined in `PERMISSION_CATALOG.md`** — Workspace, Members,
Roles & Permissions, Jobs, Candidates, Applications & Pipeline, Interviews,
Offers & Employees, Talent Pool & Templates, Platform services, AI, Commerce,
Integration, Workflow.

- Categories are **display-only** and **MUST NOT** affect enforcement
  (`PERMISSION_MODEL.md` §2; `PERMISSION_CATALOG.md` §1). Grouping is an
  organizing aid, nothing more.
- The selectable set is exactly the keys in `PERMISSION_CATALOG.md` §2
  (workspace permissions). **`system.*` keys are never selectable** in a
  workspace role; they belong to the Platform Context and are held by System
  Owners only (`PERMISSION_CATALOG.md` §3; `PERMISSION_MODEL.md` §6).
- Baseline capabilities in `PERMISSION_CATALOG.md` §1 (e.g. `workspace.create`,
  `workspace.join`, applying to a public job, managing one's own profile) are
  **not** workspace permissions and therefore **MUST NOT** appear as selectable
  role permissions.
- The builder **SHOULD** surface dependency hints so that selecting an action
  permission also prompts for the matching view permission it requires (e.g.
  `job.create` is meaningless without `job.view`; `offer.send` presupposes
  `offer.view`). Dependencies are catalogued per action in `ACCESS_POLICIES.md`;
  the Role Builder **SHOULD NOT** invent dependencies of its own.
- The catalog is the **single source of truth** for which keys exist. The builder
  **MUST** render the live catalog and **MUST NOT** hard-code a permission list;
  when a module is added its keys appear automatically
  (`PERMISSION_CATALOG.md` §4; `PERMISSION_MODEL.md` §8, invariant 5).

> Keys that are not within the workspace's subscription or enabled modules MAY be
> hidden or shown disabled for clarity, but visibility is **not** authorization
> (`PERMISSION_MODEL.md` §5). A role MAY still carry a key whose module is later
> enabled; the key simply has no effect until then.

---

## 5. Convenience Templates (Optional)

To reduce setup effort, the Role Builder MAY offer **templates** at creation time
— for example "Recruiter", "Hiring Manager", "Interviewer", "Billing Admin",
"Read-only" — each being a **suggested preset of permission keys**.

Binding rules for templates:

1. A template is a **convenience only**. Applying one **produces an ordinary,
   fully editable role** — identical in every respect to one built by hand
   (`PERMISSION_MODEL.md` §3).
2. A template-seeded role is **not special in code**. There is no template type,
   no template flag that grants privilege, and **no code path that branches on
   whether a role came from a template**.
3. After creation, the role has **no link** to its template: editing the role
   does not change the template, and changing a template does not retroactively
   alter roles already created from it.
4. Templates **MUST** draw only from `PERMISSION_CATALOG.md` keys and **MUST**
   honour deny-by-default — a template grants exactly the keys it lists and
   nothing implicit.
5. Templates are presentation-layer suggestions; they **MUST NOT** be treated as
   reserved roles, defaults, or a fallback authorization source.

> Templates make the common case fast without reintroducing system roles by the
> back door. If a template ever conferred behaviour a hand-built role could not,
> that would violate `PERMISSION_MODEL.md` §1 and §8.

---

## 6. The Owner's Direct Grant

The **workspace owner** receives a **full set of workspace permissions at
creation, via a direct grant on the owning Membership — not via a reserved
"Owner" role** (`PERMISSION_MODEL.md` §4).

- The owner's authority is therefore expressed as **permission keys held
  directly**, exactly like any other grant, and is checked the same way.
- Because the owner can hold every workspace permission, the owner can **build
  any role and assign any workspace permission without a code change**
  (`PERMISSION_MODEL.md` §8, invariant 3). This is the mechanism that makes the
  zero-default-roles model workable on day one.
- Effective permissions for any member = the **union of all granted roles'
  permissions plus any direct grants** (`PERMISSION_MODEL.md` §4). The owner's
  direct grant participates in this union like any other.
- Ownership may be moved via `workspace.transfer`, which reassigns the owner
  Membership and its direct grant without changing workspace state
  (`STATE_DIAGRAMS.md` §10). This is an audited, high-sensitivity action
  (§9; `ACCESS_POLICIES.md`).

> The owner is a **grant**, not a role. There is deliberately no "Owner" role to
> name, clone, or branch on.

---

## 7. Deletion & Reassignment Rules

Deleting a role (`role.delete`) **MUST NOT** silently strip access in a way that
surprises operators or orphans members. The following rules apply:

1. **No dangling references.** When a role is deleted, every `Membership` that
   held it MUST have that role removed from its grant set as part of the same
   operation. A member's effective permissions recompute from the remaining
   union (`PERMISSION_MODEL.md` §4).
2. **Guarded deletion of in-use roles.** Deleting a role that is **currently
   assigned** SHOULD require explicit confirmation and SHOULD offer
   **reassignment** of affected members to a replacement role before removal, so
   that access loss is intentional rather than incidental.
3. **No self-lockout.** The system **MUST** prevent an operation that would
   leave the workspace with **no member able to manage roles or members** (i.e.
   no remaining holder of `role.*` and `permission.assign`). The owner's direct
   grant (§6) normally satisfies this; deletion flows MUST NOT bypass it.
4. **Deletion is not data loss for audit.** Removing a role does not erase its
   history; the create/update/delete events remain in the audit trail (§9).
5. **Least surprise on reduction.** Removing permissions from a role (rather than
   deleting it) immediately narrows access for all holders at the next check;
   editors SHOULD be warned when a reduction affects active members.

Reassignment itself is a membership change (assign/remove role) and is governed
by §8.

---

## 8. Composite Actions

Some Role Builder operations require **more than one permission** and MUST be
authorized against **all** required keys (`PERMISSION_MODEL.md` §5;
`PERMISSION_CATALOG.md` §4, rule 1). Notable cases:

- **Assigning or removing a role on a member** requires both the authority to
  change a membership and the authority to assign permissions — conceptually
  `member.update` **and** `permission.assign`. Missing either ⇒ denied.
- **Editing a role's permission set** requires `role.update` and, for the act of
  changing which keys are bundled, `permission.assign`.
- **Cloning then assigning** a role is two checks: `role.clone` to create, then
  the role-assignment composite above to grant it.

The authoritative per-action mapping (Required Permission(s), Dependencies,
Denied Behaviour) lives in `ACCESS_POLICIES.md`. The Role Builder defers to it
and MUST NOT define a weaker check locally.

---

## 9. Auditing of Role Changes

All permission- and role-affecting actions are audited — **who, when, where, and
what changed** (`PERMISSION_MODEL.md` §7). The Role Builder's audited events
include, at minimum:

- role **created**, **renamed/updated**, **cloned**, **deleted**;
- permissions **assigned to / revoked from** a role;
- a role **assigned to / removed from** a member;
- **ownership transferred** (the owner's direct grant moving to another member).

Audit records MUST be immutable and MUST capture the actor, the target role or
membership, the before/after permission sets where applicable, the workspace
scope, and a timestamp. The canonical event names and payloads are defined in
`AUDIT_EVENTS.md`, which this document defers to. Audit entries are surfaced via
the workspace audit log (`audit.view` / `audit.export` in
`PERMISSION_CATALOG.md`).

---

## 10. Concurrency & Least-Privilege Guidance

**Concurrency.**
- Role edits **SHOULD** be safe under concurrent modification. Implementations
  SHOULD use optimistic concurrency (e.g. a version token) so that two
  simultaneous edits cannot silently overwrite one another; the later write
  SHOULD be rejected and retried against current state.
- Because effective permissions are computed as a **union at check time**
  (`PERMISSION_MODEL.md` §4), a role edit propagates to all current holders on
  their next authorization check. Editors SHOULD understand that changes are not
  scoped to one member.
- Deleting a role and assigning it concurrently MUST resolve deterministically:
  a role being deleted MUST NOT be newly assignable, and assignment to a
  just-deleted role MUST fail rather than recreate a dangling reference (§7.1).

**Least privilege.**
- Roles **SHOULD** grant the **minimum permissions** required for their purpose.
  Prefer several narrow roles over one broad role; prefer cloning-and-trimming
  over copying a powerful role wholesale.
- Sensitive keys — `permission.assign`, `role.delete`, `workspace.transfer`,
  `workspace.delete`, `billing.manage`, `ai.keys.manage`, `apikey.manage`,
  `member.remove` — **SHOULD** be confined to a small number of trusted roles and
  reviewed regularly.
- Granting a view permission (e.g. `candidate.view`) without the corresponding
  action permissions is the recommended pattern for read-only participation.
- The owner's direct full grant (§6) **SHOULD** be treated as a privileged
  account: organizations SHOULD avoid using it for day-to-day work and SHOULD
  build scoped roles for routine operations.
- Periodic review of roles and assignments **SHOULD** be performed; the audit
  trail (§9) supports this.

---

## 11. Invariants (Role Builder)

1. A role is workspace-scoped **data**; there are **no reserved/system roles**
   and **no code branches on a role name** (`PERMISSION_MODEL.md` §1).
2. Only permission **keys** from `PERMISSION_CATALOG.md` are selectable;
   `system.*` keys and baseline capabilities are **not** selectable.
3. Templates produce **ordinary, editable, non-special** roles with no live link
   to the template.
4. The owner's authority is a **direct grant**, not an "Owner" role.
5. Deleting a role removes it cleanly from all memberships and never causes a
   management lockout (§7).
6. Every Role Builder action is permission-checked server-side and audited
   (`PERMISSION_MODEL.md` §5, §7; `AUDIT_EVENTS.md`).

---

### Related Documents

`PERMISSION_MODEL.md` · `PERMISSION_CATALOG.md` · `ACCESS_POLICIES.md` ·
`SECURITY_MATRIX.md` · `AUDIT_EVENTS.md` · `SYSTEM_PERMISSIONS.md` ·
`WORKSPACE_PERMISSIONS.md` · `STATE_DIAGRAMS.md`
