# Audit Follow-up — Gate Wiring, Consolidation & Dead-Code Report

> **Status:** Open · **Last updated:** 2026-07-01
> Produced after the parallel legacy/dead-code re-audit (multi-agent, grep-verified)
> and acted on per the approved, **backward-compatible-only** scope. **No data was
> deleted and no compat-breaking change was made.** Drop-column / drop-table /
> Auth-Billing-legacy removals remain **out of scope** (see `REQUIRES_APPROVAL.md`).

---

## 1. Permission-gate wiring (the 21 never-enforced catalog keys)

A per-key feasibility audit mapped each of the 21 keys to a real action + its
current gate. Verdict distribution:

| Verdict | Count | Keys |
|---|---|---|
| **WIREABLE-SAFE** | 1 | `permission.assign` |
| **NO-ACTION** (no endpoint exists to gate — catalog-only placeholder) | 6 | `workspace.delete`, `member.update`, `candidate.export`, `job.delete`, `workflow.delete`, `workflow.settings` |
| **WIREABLE-RISKY** (would break existing custom-role users or broaden owner-only actions, or fights the deliberate coarse-grained design) | 14 | `workspace.update/settings/archive/restore/transfer`, `application.view/update`, `workflow.update/execute/publish/pause/logs/variables/templates` |

**Wired now (backward-compatible):** `permission.assign`. `RolesController::update`
already required `role.update`; it now enforces the documented **composite** — a
role's *permission grants* can only be changed by an actor who also holds
`permission.assign`, while renaming remains available with `role.update` alone (the
submitted grants are preserved, not applied, when the actor lacks the right). The
workspace **owner holds every permission by direct grant**, and **no default roles
are seeded** (`WorkspaceCreator`), so no existing setup can be locked out.

**Not wired (and why):**
- The **6 NO-ACTION** keys have no endpoint performing that action (e.g. `job.delete`
  — only *archive* exists under `job.archive`; `workspace.delete` — only
  *archive/deactivate* exist). Wiring is impossible without inventing new actions.
  They remain seeded catalog contracts (asserted by role tests); removing them needs
  approval.
- The **14 WIREABLE-RISKY** keys are, by design, collapsed into coarser enforced
  keys (`workflow.create`/`workflow.view`, `pipeline.*`, `settings.*` — see
  `docs/WORKFLOW_PERMISSIONS.md`), or govern **owner-only** lifecycle actions
  (`workspace.archive/restore/transfer`) that currently use owner gates rather than
  `->can()`. Splitting them would either lock out roles holding only the coarse key
  or *broaden* owner-exclusive actions — both break backward compatibility. They are
  therefore left as catalog contracts pending an explicit, separately-reviewed
  authorization-model change.

---

## 2. Duplicate-logic consolidation (done, behaviour-preserving)

Extracted the copies flagged in `REQUIRES_APPROVAL.md §4` into single-source
`app/Support` helpers; every call site delegates, so output is identical:

- **`Slug::make`** — job / program / learning-path slug base (was 3 copies).
- **`Filename::safe`** — upload / CV filename sanitiser (was 2 copies).
- **`Position::next`** — `COALESCE(MAX(position),-1)+1` append position (was 6
  copies), null-safe `<=>` for nullable scope columns.
- **`TemplateTokens::resolve`** — `{{token}}` resolver shared by `WorkflowEngine` +
  `ActionExecutor` (byte-identical copies). `PromptEngine`'s variant is left as-is
  (different value contract: no JSON-encode, template fallback) to avoid any drift.

Pinned by `tests/Unit/SupportHelpersTest.php` (asserts the exact legacy behaviour).

---

## 3. Dead-code / unused-artifact re-audit (report only — nothing deleted)

Parallel grep-verified sweep across all 8 categories. **Zero 🟢 safe-to-delete
items that are worth removing without a decision.** Summary:

- **Views (102):** 0 unused — all rendered, a layout, or a relative-path partial.
- **Routes (279 pairs):** 0 broken, 0 dead actions.
- **Classes / files:** only the `Middleware` contract (deliberate forward-looking
  stub, 0 implementers) is reference-free; no orphan/backup/empty files; PSR-4 clean.
- **Tables:** only the 3 orphan Auth tables (`user_sessions`, `password_resets`,
  `remember_tokens`) are code-unreferenced — **data-bearing, off-limits** (approval).
  `subscriptions`/`invoices` are **live** (refutes any "Billing dead island" for the
  tables themselves).
- **Permissions:** the 21 above + `workspace.view` (sidebar-only) are unenforced —
  seeded **contract**, asserted by role tests; not removable without approval.
- **Config:** `app.url`, `app.timezone`, `app.locale` are defined but never read
  (harmless — the app is UTC-only via `gmdate()` and routes generate URLs). Left as
  conventional keys.
- **Public methods on `final` classes:** the 17 confirmed-dead methods from
  `REQUIRES_APPROVAL.md §3` still have zero call sites, plus a handful of new
  zero-reference module/Core-framework methods and ~24 test-only methods (mostly the
  Billing legacy island). All are **plausibly-intended API surface** — removal is a
  judgment call, so they are **reported, not removed**, per the standing rule.

**Net:** nothing was deleted. Everything reference-free is either a deliberate
forward stub, a seeded contract, a data-bearing table, or a plausibly-intended
utility — each carries ≥1% doubt and stays until explicitly approved.
