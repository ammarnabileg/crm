# 50 — Continuous Project Audit (التدقيق المستمر للمشروع)

**Binding.** After finishing **every** phase, audit the **entire** project — not
only the part you just changed — find defects across all the categories below, and
**fix them before starting the next phase**. The quality of the whole project is
your responsibility, not just the code written in the current phase.

## Related Documents

- [49-Development-Workflow](49-Development-Workflow.md) — the per-feature lifecycle this audit gates between phases
- [47-Enterprise-Architecture-Standards](47-Enterprise-Architecture-Standards.md) · [48-Multi-Tenant-RBAC-Bible](48-Multi-Tenant-RBAC-Bible.md)
- [40-QA-Checklist](40-QA-Checklist.md) · [42-Code-Review-Checklist](42-Code-Review-Checklist.md) · [34-Security](34-Security.md) · [35-Performance](35-Performance.md) · [46-Architecture-Review](46-Architecture-Review.md)

## Purpose (الهدف)

To stop defects from accumulating: each phase boundary is a gate where the whole
system is swept for breakage and drift, and made healthy again before new work.

## Why It Exists (سبب وجوده)

Local changes have global effects — a rename, a new permission, a schema change can
silently break a far-away page, route or document. Auditing only the touched code
lets regressions hide. Whole-project ownership is the rule.

## Architecture — the audit dimensions

After each phase, scan the entire project for:

- **Broken pages** — any view/screen that errors or renders empty unintentionally.
- **Dead buttons** — buttons/links/actions with no working target.
- **Broken routes** — routes pointing at missing controllers/methods, or referenced
  routes that don't exist.
- **Unused APIs** — endpoints no client calls (remove or wire), and called endpoints
  that don't exist.
- **Missing permissions** — routes/APIs/actions without a permission gate, or
  permission keys referenced but not in the catalogue.
- **Multi-Tenant problems** — any tenant-scoped query that can leak across
  workspaces; non-fail-closed scope; missing `workspace_id` filters.
- **RBAC problems** — role/permission gaps, conflicts, or checks that bypass policy.
- **Performance problems** — N+1 queries, missing indexes on hot paths, unbounded
  queries, excessive query counts, memory blow-ups.
- **Security problems** — SQL injection, XSS, CSRF, broken access control, mass
  assignment, unsafe file uploads, missing rate limits, tenant escaping.
- **Database problems** — orphan rows, missing/incorrect FKs, missing indexes,
  schema vs blueprint drift, ENUM columns (forbidden).
- **UI/UX problems** — alignment/spacing/typography, missing empty/loading/error
  states, broken responsive/RTL, accessibility gaps.
- **Doc ↔ code conflicts** — `/docs` describing something the code doesn't do (or
  vice-versa); stale references after renames.

## Workflow

1. Finish a phase (a feature/module per [49](49-Development-Workflow.md)).
2. Run the full audit across **all** dimensions above (whole project).
3. Triage findings into real defects vs. accepted/known items.
4. **Fix the real defects** (and re-run tests + targeted runtime checks).
5. Record the result in a **Progress Report** (DW-17) — what was found, fixed, and
   what remains with its risk.
6. Only then begin the next phase.

## Business Rules

- **CA-1 — Whole-project scope.** The audit covers the entire codebase + docs, not
  just the current diff.
- **CA-2 — Gate, not afterthought.** The next phase does not start until the audit
  passes and real defects are fixed.
- **CA-3 — Stop conditions inherited.** An architectural flaw, DB conflict, roles
  conflict, tenant-isolation hole or security issue halts new work until fixed +
  documented (mirrors [49](49-Development-Workflow.md) DW-14).
- **CA-4 — No silent drift.** Any doc↔code conflict found is reconciled (fix the
  code or update the doc) — `/docs` stays the single source of truth.
- **CA-5 — Evidence.** Findings and fixes are reported with concrete evidence
  (counts, route lists, test results), never a bare "looks fine".

## Permissions

Governance only — grants nothing. Audited features keep their own permission gates
([11-Permissions-Matrix](11-Permissions-Matrix.md)).

## Validation

The audit itself validates the system: route↔controller↔view wiring, permission
coverage on every route/API, tenant-scope on every tenant query, FK/index integrity,
and doc↔code consistency.

## Edge Cases

- A finding may be a **false positive** (e.g. an intentionally not-yet-built nav
  item) — record it as accepted with the reason rather than "fixing" it wrongly.
- A fix that would require an architecture change triggers DW-13 (docs first).

## Security

Security is a first-class audit dimension (see the list above) and is also run as a
dedicated audit per [34-Security](34-Security.md); never defer security findings.

## Performance

Performance is audited every phase (N+1, indexes, query counts, memory, queue) so
regressions are caught at the boundary, not in production ([35-Performance](35-Performance.md)).

## Testing

Each audit ends with the full automated suite green plus targeted runtime smokes for
the dimensions that static checks can't prove (HTTP status of every page/route,
permission 403s, tenant isolation). See [39-Testing-Strategy](39-Testing-Strategy.md).

## Future Expansion

- An automated audit runner (script) that produces the route/permission/tenant/FK
  coverage report and a doc↔code drift diff on demand, feeding the Progress Report.

## Open Questions

- Cadence for very small phases — current reading: every phase, however small,
  gets at least the fast static sweep (routes/permissions/lint/tests).
