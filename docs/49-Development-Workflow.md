# 49 — Development Workflow (دورة تطوير المميزات)

**Binding.** Treat the project as a full delivery team, not a single coder. Every
feature passes through the same lifecycle, in order, and no stage may be skipped or
declared done before the previous one succeeds. This document is the authoritative
capture of the Development Workflow Bible and governs all feature work.

## Related Documents

- [00-README](00-README.md) — documentation map + the documentation-first rule
- [47-Enterprise-Architecture-Standards](47-Enterprise-Architecture-Standards.md) — the binding architecture standard each feature is built against
- [48-Multi-Tenant-RBAC-Bible](48-Multi-Tenant-RBAC-Bible.md) — tenancy + RBAC constitution
- [50-Continuous-Project-Audit](50-Continuous-Project-Audit.md) — the whole-project audit run after every phase
- [39-Testing-Strategy](39-Testing-Strategy.md) · [40-QA-Checklist](40-QA-Checklist.md) · [41-Coding-Standards](41-Coding-Standards.md) · [42-Code-Review-Checklist](42-Code-Review-Checklist.md) · [34-Security](34-Security.md) · [35-Performance](35-Performance.md) · [29-API-Architecture](29-API-Architecture.md)

## Purpose (الهدف)

To guarantee that every feature ships **complete, correct, secure, tenant-safe,
documented and verified** — quality over speed, completeness over feature count.

## Why It Exists (سبب وجوده)

Partial features (dead buttons, broken routes, missing permissions, undocumented
behaviour, hard-coded values) accumulate into an unmaintainable, insecure system.
A single mandatory lifecycle makes "done" mean the same thing every time.

## Architecture — the 16-stage feature lifecycle

Each feature flows through these stages **in order**; a stage starts only after the
previous one passes:

1. **Requirements Analysis** — what, why, who, acceptance criteria.
2. **Architecture Review** — fit against [47](47-Enterprise-Architecture-Standards.md); does it need an architecture change? (if yes, see DW-13).
3. **Database Review** — tables/columns/indexes/FKs vs [06-ERD](06-ERD.md) + the database blueprint; config-driven, no ENUMs.
4. **UX Review** — flows, states (empty/loading/error), responsive, RTL/LTR, accessibility.
5. **Security Review** — threat surface up front (the [Security Rules](#security) list).
6. **API Design** — endpoints, auth, authorization, validation, errors, pagination/filter/sort ([29](29-API-Architecture.md)).
7. **Backend Development** — domain/service/repository/DTO per [47](47-Enterprise-Architecture-Standards.md).
8. **Frontend Development** — views, components, responsive, states.
9. **Integration** — wire backend ↔ frontend ↔ routes ↔ permissions.
10. **Automated Testing** — unit + feature + security tests (the [Self-Testing matrix](#testing)).
11. **Manual QA** — run it: every page, button, tab, API, permission.
12. **Performance Review** — the [Performance Rules](#performance) (N+1, indexes, query count, caching).
13. **Security Audit** — re-check against the [Security Rules](#security) with the code in hand.
14. **Refactoring** — remove duplication, large classes/methods, dead code, unused imports.
15. **Documentation Update** — architecture, ERD, API, permissions matrix, QA checklist, CHANGELOG.
16. **Final Approval** — Definition of Done satisfied; then (and only then) the feature is "done".

## Workflow

- **Documentation first (DW-0).** Before building any feature, search `/docs`. If
  the relevant documentation does not exist, **write it first**, then implement —
  code conforms to docs, never the reverse.
- A feature is built top-to-bottom through the 16 stages; the
  [Continuous Project Audit](50-Continuous-Project-Audit.md) runs after the phase
  completes, before the next phase starts.
- After every phase, produce a **Progress Report** (DW-17).

## Business Rules

- **DW-1 — No stage skipping.** Stages run 1→16 in order; the next never starts
  before the previous passes.
- **DW-2 — Feature completeness.** A feature is not complete unless it has ALL of:
  Business Logic · Database · API · Validation · Permissions · Frontend ·
  Responsive Design · Notifications · Logs · Audit · Tests · Documentation.
- **DW-3 — Definition of Done.** A feature is done only when: it runs with no
  errors; every page, button, tab, API and permission works; **no** console errors,
  **no** network errors, **no** placeholders, **no** `TODO`, **no** hard-coded
  values; and the documentation is updated.
- **DW-4 — No fake completion.** Never write "Done / Completed / Finished /
  Production Ready" for a feature that has not been fully tested.
- **DW-5 — Routing rule.** No route is created without: Permission · Validation ·
  Controller · Tests · Documentation.
- **DW-6 — Database rule.** Every migration has, as needed: Indexes · Foreign Keys ·
  Constraints · Soft Deletes · UUID. (Config-driven, no ENUMs — per the DB blueprint.)
- **DW-7 — API rule.** Every API has: Authentication · Authorization · Validation ·
  Error Handling · Pagination · Filtering · Sorting · Documentation.
- **DW-8 — Mandatory UI review** before finishing a page: Alignment · Spacing ·
  Typography · Responsive · Dark-mode support · Loading · Empty · Error states ·
  Accessibility.
- **DW-9 — Self-testing** after each feature: see the [Testing](#testing) matrix.
- **DW-10 — Regression testing.** After a feature, verify all previous features
  still work; a regression must be fixed before continuing.
- **DW-11 — Refactoring pass** after each module (DW-14 list).
- **DW-12 — Documentation update** after each module: Architecture · ERD · API ·
  Permissions Matrix · QA Checklist · CHANGELOG.
- **DW-13 — Architecture protection.** If a feature needs an architecture change,
  do NOT code first — update `/docs`, review ERD + RBAC + Multi-Tenant + API, then implement.
- **DW-14 — Stop conditions.** On discovering an architectural flaw, a database
  conflict, a roles conflict, a tenant-isolation problem, or a security issue:
  STOP coding, fix the problem, update the docs, then resume.
- **DW-15 — Golden Rule.** Quality > speed; feature completeness > feature count.
  Never add a page, button, route or API unless it works fully — if it is not
  100% ready, do not create it at all.
- **DW-17 — Progress report** after each phase: what was done, what changed, what
  was fixed, what needs review, current risks, future risks.

## Permissions

This document is process governance; it grants nothing. Feature permissions follow
[11-Permissions-Matrix](11-Permissions-Matrix.md) and the RBAC constitution
([48](48-Multi-Tenant-RBAC-Bible.md)) — every route/API is permission-gated (DW-5/DW-7).

## Validation

Validation is mandatory at the boundary of every feature (DW-2/DW-5/DW-7):
server-side validation on every write, typed DTOs, and friendly error states (DW-8).

## Edge Cases

The self-test matrix explicitly exercises Empty / Loading / Error states and the
edge cases of each operation (DW-9). A feature that only handles the happy path is
not done (DW-3).

## Security

Every feature is reviewed (stage 5) and audited (stage 13) against:
SQL Injection · XSS · CSRF · Broken Access Control · Mass Assignment ·
File-Upload Attacks · Rate Limiting · Tenant Escaping. See [34-Security](34-Security.md).

## Performance

Always review: N+1 Queries · Caching · Indexes · Query Count · Memory Usage ·
Queue Usage (stage 12). See [35-Performance](35-Performance.md).

## Testing

**Self-testing matrix (DW-9)** — after each feature, test: Create · Read · Update ·
Delete · Search · Filter · Sort · Export · Import · Validation · Permissions ·
Tenant Isolation · Edge Cases · Empty State · Loading State · Error State.
**Regression (DW-10):** the full prior suite must stay green. See
[39-Testing-Strategy](39-Testing-Strategy.md) + [40-QA-Checklist](40-QA-Checklist.md).

## Future Expansion

- A lightweight per-feature checklist file (generated from DW-2/DW-3) committed
  alongside each module, ticked through the 16 stages.
- CI gates that fail a build on `TODO`/placeholder/hard-coded markers and on
  routes lacking a permission or test.

## Open Questions

- Whether "Notifications" (DW-2) is mandatory for every feature or only
  state-changing ones — current reading: required wherever a user should be informed.
