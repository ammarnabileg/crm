# VERSIONING POLICY — HaHireAI

> **Status:** Adopted (Phase 3) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `ENTITY_CATALOG.md`.

---

## 0. Purpose & Scope

This document is the **authoritative policy** for **data version history** in
HaHireAI: which entities keep prior versions, **why**, how versions are created,
viewed, restored, and diffed, and how long they are retained. It governs
*versioning of data* — not code or schema versioning (migrations are
forward-only and governed by `DATABASE_GUIDE.md` §12; document versions are the
header `Version:` field). The set of versioned entities and their `(immutable)`
version stores are owned by `ENTITY_CATALOG.md`; this policy MUST NOT be read as
the entity list.

**Interpretation keywords** (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST/MUST NOT** rule is binding; a violation is a
defect.

**Versioning is data, not code.** A new version is a **row**, created by user
action at runtime — never a code change, deployment, or migration. Template,
prompt, and workflow authors evolve their content as data
(`PERMISSION_MODEL.md`, `WORKSPACE_MODEL.md`: behavior is data, not code).

---

## 1. Why HaHireAI Versions Data

Versioning exists to make **authored, evolving, consequential** content
**auditable and reversible** (`DATABASE_GUIDE.md` §1, principle 4 "Auditable").
An entity SHOULD be versioned when **all** of the following hold:

1. **Authored content** that a human edits over time (not derived/computed data).
2. **Consequence on history**: past records were created against a *specific*
   prior version, so the old version must remain legible (e.g. an application was
   submitted against a particular job description).
3. **Reversibility value**: being able to view, compare, and restore a prior
   version is genuinely useful to the business.

Entities that are merely *mutable state* (e.g. an application's current stage)
are **not** versioned — their change history, where needed, is captured by
dedicated immutable trails (`application_stage_history`) governed by
`ARCHIVING_POLICY.md`, not by this policy.

---

## 2. The Snapshot Model (binding)

HaHireAI versions by **immutable snapshot rows**, not by mutating a single row in
place.

- A version is a **point-in-time snapshot** stored as its own row. Version rows
  are **append-only**: once written they are **never updated and never deleted**
  outside retention (§6). Version stores listed in `ENTITY_CATALOG.md` as
  **(immutable)** (e.g. `job_versions`) carry neither `updated_at` nor
  `deleted_at`.
- Each version row SHOULD carry: a `version` ordinal (monotonic per parent), the
  **full content snapshot** (typically a `content`/`definition`/`template` JSON
  blob — `DATABASE_GUIDE.md` §8), the **actor** who created it, the **trigger**
  (§3), and `created_at` (UTC).
- The **live entity** holds the *current* version (its present content and/or a
  pointer to the current version row). Reading the entity returns the current
  state; reading history returns the snapshot rows.
- Snapshots are **intentional point-in-time facts**, which `DATABASE_GUIDE.md`
  §10 explicitly permits (they record history; they are **not** forbidden
  duplication). They reference related entities by `CHAR(26)` id and MUST NOT
  cross module boundaries via foreign keys (`DATABASE_GUIDE.md` §11).
- Version rows are **workspace-scoped where their parent is** and pass the tenant
  guard (`DATABASE_GUIDE.md` §6); a global library (`prompt_templates`) versions
  at platform scope.

---

## 3. What Triggers a New Version

A new snapshot is created by an explicit, meaningful authoring event — **not** on
every keystroke or autosave.

| Trigger | Applies to | Behavior |
|---|---|---|
| **Publish** | `jobs`, `workflows`, `workspace_prompts`, `prompt_templates`, published `templates` | Publishing a draft **MUST** create an immutable version snapshot of what was published. |
| **Edit-after-publish** | versioned entities | A material edit to already-published content **MUST** create a new version (the prior version is preserved). |
| **Restore** | versioned entities | Restoring an older version **MUST** create a **new** version equal to the restored content (forward-only history; see §4) — it MUST NOT rewrite or delete intervening versions. |
| **Significant settings change** | key `settings` (governed/security-relevant `workspace_settings`) | A change to a *governed* setting SHOULD snapshot the prior value for accountability. Routine, low-risk settings need not be versioned. |
| **Document revision** | important `documents` (`files`) | Replacing an important file (e.g. a controlled template/offer document) SHOULD create a new file version snapshot rather than overwriting silently. |

- Draft autosaves and trivial edits **SHOULD NOT** spawn versions; only
  deliberate publish/edit/restore events do. This keeps history meaningful and
  bounded (`DATABASE_GUIDE.md` §1, principle 6 "Scalable").
- Creating a version is an **audited** action where the entity is governed
  (`AUDIT_POLICY.md` §7 — e.g. job published, prompt published).

---

## 4. View, Restore & Diff

- **View:** Authorized users (subject to the entity's view permission,
  deny-by-default per `PERMISSION_MODEL.md` §5) can list an entity's versions and
  open any historical snapshot read-only.
- **Diff:** The system SHOULD present a field-level **comparison** between any two
  versions (or a version and current), computed from the stored snapshots — no
  separate "diff" is stored; it is derived on demand (`DATABASE_GUIDE.md` §10:
  derive, don't duplicate).
- **Restore:** Restoring sets the entity's current content to a chosen prior
  snapshot **by appending a new version** (§3). History is **forward-only**:
  restore never deletes or edits the versions in between, so the trail of "what
  was live when" stays intact. This mirrors the platform's forward-only stance
  (`DATABASE_GUIDE.md` §12, conceptually applied to data).
- Restoring is **permission-gated** (the same edit/publish permission for that
  entity) and **audited**.

---

## 5. Per-Entity Versioning Map

Entities and their version stores are authoritative in `ENTITY_CATALOG.md`; this
table assigns the **policy**. "Versioned?" = keeps prior versions as snapshots.

| Entity (table) | Versioned? | Version store | Trigger | Retention (SHOULD) |
|---|---|---|---|---|
| `jobs` (workspace) | **Yes** | `job_versions` (immutable) | Publish / edit-after-publish / restore | Lifetime of the workspace; versions retained while job retained |
| `job_versions` (workspace) | n/a (is the store) | — (append-only snapshots) | Written by `jobs` events | Same as `jobs` |
| `templates` (workspace) | **Yes** | Per-row `version` snapshots (in `templates`, immutable history) | Publish / material edit | Lifetime of the workspace |
| `workspace_prompts` (workspace) | **Yes** | `version` field + snapshot rows | Publish / edit of a prompt | Lifetime of the workspace |
| `prompt_templates` (global library) | **Yes** | Versioned global rows | Publish / edit of library prompt | Platform retention |
| `workflows` (workspace) | **Yes** | `version` snapshots of `definition` (draft→published) | Publish / edit-after-publish / restore | Lifetime of the workspace |
| Key `settings` (governed `workspace_settings`) | **Selective** | Prior-value snapshot of governed keys | Significant/security-relevant change | Per workspace policy; aligns with audit retention |
| Important `documents` (`files`) | **Selective** | File version snapshots | Controlled-document replacement | Per workspace storage/retention policy |
| Mutable state (e.g. `applications` stage) | **No** (state, not versioned content) | History via `application_stage_history` (immutable) — see `ARCHIVING_POLICY.md` | n/a | n/a |
| Configuration objects (e.g. `pipelines`, `roles`) | **No** by default | — (changes audited per `AUDIT_POLICY.md`) | n/a | n/a |

> **Note.** Entities not listed as versioned in `ENTITY_CATALOG.md` are **not**
> versioned. Their change accountability comes from the **audit trail**
> (`AUDIT_POLICY.md`) and, where relevant, dedicated immutable history tables —
> not from snapshot versioning. Versioning is added by *adding* a version store,
> never by retrofitting mutation-in-place (`DATABASE_GUIDE.md` §1, principle 2).

---

## 6. Retention of Versions

- Version history follows the **retention of its parent entity** unless a longer
  legal/compliance window applies (`ARCHIVING_POLICY.md` §4). While a `jobs` row
  is retained, its `job_versions` are retained.
- Version stores are **immutable**: old versions are **never silently pruned** in
  normal flows. Any bounded reduction of very old versions (e.g. a "keep last N
  major versions" policy for an extremely high-churn entity) MUST be an explicit,
  configured, logged retention action — never an ad-hoc delete — and MUST respect
  legal holds (`ARCHIVING_POLICY.md`).
- When a parent is lawfully **erased** (GDPR / data-subject request,
  `ARCHIVING_POLICY.md` §5), its version snapshots are erased/anonymized with it
  to the same standard; versioning MUST NOT become a backdoor that preserves
  content the business is legally required to remove.
- Retention windows are **policy/plan configuration**, not hard-coded
  (consistent with `WORKSPACE_MODEL.md` §4).

---

## 7. Conformance Checklist

A versioned entity is conformant only if **all** apply:

- [ ] It is listed as versioned in `ENTITY_CATALOG.md`, with a defined version
      store (§5).
- [ ] Versions are **immutable snapshot rows** (append-only; no update/delete
      outside retention) (§2).
- [ ] New versions are created on **publish / edit-after-publish / restore** (and
      governed settings/document changes) — not on every autosave (§3).
- [ ] The live entity exposes the **current** version; history is read-only (§2,
      §4).
- [ ] Diffs are **derived** from snapshots, not stored as a second source of
      truth (§4, `DATABASE_GUIDE.md` §10).
- [ ] Restore is **forward-only** (appends a new version; never rewrites
      history), permission-gated, and audited (§4).
- [ ] Version stores are workspace-scoped where their parent is and pass the
      tenant guard (§2, `DATABASE_GUIDE.md` §6).
- [ ] Versions follow parent retention; lawful erasure removes versions too;
      legal holds respected (§6).

---

### Related Documents
`ENTITY_CATALOG.md` · `DATABASE_GUIDE.md` · `ARCHIVING_POLICY.md` ·
`AUDIT_POLICY.md` · `DOMAIN_MODEL.md` · `PERMISSION_MODEL.md` ·
`WORKSPACE_MODEL.md` · `STATE_DIAGRAMS.md`
