# TESTING GUIDE — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md` (§13).

---

## 0. Purpose & Scope

This guide elaborates the binding testing essentials of `PROJECT_CONSTITUTION.md`
§13 into concrete practice for every module and contributor (human or AI). It
defines *how* we test; the Constitution states the *law*. Where any statement
here appears to conflict with the Constitution, **the Constitution wins**.

Interpretation keywords (**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**,
**MAY**) follow RFC 2119. A **MUST / MUST NOT** rule is binding; a violation is a
defect that blocks merge.

> Code snippets are **illustrative examples only** — not implementation, not
> normative. The normative content is the prose rules.

---

## 1. Testing Philosophy

- **Tests are part of the deliverable.** Per the Constitution, each module
  **MUST** ship tests under `Tests/` (§9; `ARCHITECTURE.md` §3). Code without
  tests is incomplete.
- **The domain is where correctness lives.** The richest, fastest tests target
  Domain logic; integration confirms the wiring; a thin layer of feature/e2e
  proves the whole works.
- **Fail closed, prove it.** Security-critical behavior — **multi-tenant
  isolation** and **permission checks** — MUST be proven with explicit negative
  tests, not assumed (see §10).
- **Red blocks merge.** A failing gate (tests, static analysis, coding standard,
  dependency audit) **MUST** block the pull request (Constitution §13, §14).

---

## 2. The Test Pyramid

HaHireAI follows a **test pyramid** (Constitution §13): many fast unit/domain
tests at the base, fewer integration tests in the middle, and a thin layer of
feature/e2e tests at the top.

```
            ▲  fewer, slower, broader
   ┌─────────────────────────┐
   │   Feature / E2E          │   whole-request / browser (e2e from Phase 16)
   ├─────────────────────────┤
   │   Integration            │   module + real DB; repositories; cross-layer
   ├─────────────────────────┤
   │   Unit / Domain          │   entities, value objects, domain services — pure
   └─────────────────────────┘
            ▼  many, fast, narrow
```

| Tier | Scope | DB? | Speed | Bulk of suite |
|---|---|---|---|---|
| **Unit / Domain** | Pure domain logic in isolation; collaborators mocked | No | Fastest | Largest |
| **Integration** | Module use cases against a **real MySQL**; repositories, tenant guard, events | Yes | Medium | Moderate |
| **Feature / E2E** | Full request lifecycle; browser e2e from **Phase 16** | Yes | Slowest | Smallest |

**Distribution rule:** the suite **MUST** be pyramid-shaped. An inverted pyramid
(mostly slow e2e) is a defect to be refactored.

---

## 3. Tooling

- **PHPUnit is the test runner** — a **dev dependency, not a framework**
  (Constitution §13). It is installed via Composer under `require-dev` and MUST
  NOT leak into production (`composer install --no-dev` in prod — see
  `DEPLOYMENT_GUIDE.md`).
- **Static analysis:** **PHPStan and/or Psalm** at the project's agreed max level.
- **Coding standard:** **PHP_CodeSniffer** enforcing **PSR-12** (Constitution §6).
- **Dependency audit:** **`composer audit`** for known vulnerabilities.
- Test doubles use plain PHP / PHPUnit mocks; no framework test harness is
  introduced (no Laravel/Symfony test kits — Constitution §5).

All of the above run locally and in CI; the same commands gate both (§8).

---

## 4. Per-Module Test Layout

Each module owns its tests under `Tests/`, mirroring the canonical module anatomy
(Constitution §8; `ARCHITECTURE.md` §3):

```
/modules/<Module>/Tests/
  Unit/         Pure domain tests (entities, value objects, domain services)
  Integration/  Use cases + real DB; repositories; tenant guard; events
  Feature/      End-to-end behavior through the Application/Presentation surface
```

- Cross-module / whole-system tests live in the top-level `/tests` directory
  (Constitution §8 Folder Standards).
- A module's tests MUST exercise its **public surface** (`Contracts/` + published
  events) and its internal Domain — but MUST NOT reach into *another* module's
  internals (respect the same boundaries as production code; `ARCHITECTURE.md` §4).

---

## 5. Naming & Structure (AAA)

- **Naming.** Test classes mirror the unit under test with a `Test` suffix
  (`JobTest`, `ApplicationPipelineTest`). Test methods describe behavior in
  intent-revealing names (`it_denies_export_without_permission`,
  `move_to_stage_rejects_invalid_transition`).
- **AAA.** Every test follows **Arrange → Act → Assert**, visually separated.
  One logical behavior per test; assert on outcomes, not incidental detail.

```php
// EXAMPLE ONLY — AAA structure; illustrative, not implementation
public function it_rejects_invalid_stage_transition(): void
{
    // Arrange
    $pipeline = ApplicationPipeline::fromStage(Stage::Applied);
    // Act + Assert
    $this->expectException(InvalidTransition::class);
    $pipeline->moveToStage(Stage::Hired); // skipping required stages
}
```

---

## 6. Fixtures & Factories

- **Factories** build valid domain objects/rows with sensible defaults and
  explicit overrides for the case under test. Prefer factories over hand-built
  arrays duplicated across tests.
- **Fixtures** seed known data for integration tests. Every fixture that creates
  workspace-scoped data **MUST** set an explicit `workspace_id` so isolation can
  be asserted (§10).
- Test data uses the project's ID strategy (**ULID** primary keys — Constitution
  §15 / naming) so tests reflect production identity behavior.
- Factories/fixtures live with the module's `Tests/` and MUST NOT depend on
  another module's internals.

---

## 7. Database-Backed Integration Tests & Isolation

- Integration tests run against a **real MySQL 8+** instance (no ORM — we test the
  bespoke Database layer and repositories as they actually run).
- **Test isolation between tests** is mandatory: each test runs against a clean,
  known state. Use a **transaction-rollback per test** (preferred) or
  truncate/migrate-fresh strategy; tests MUST NOT depend on order or on residue
  from a previous test.
- Migrations are applied to the test database via the **bespoke migration runner**
  (the same forward-only runner used in deployment — `DEPLOYMENT_GUIDE.md`), so
  schema under test matches production.
- The **tenant guard** is exercised here: integration tests confirm that
  workspace-scoped repositories filter by `workspace_id` (§10).

> "Isolation" here means two things, both required: (a) **test isolation** — tests
> don't bleed into each other; (b) **tenant isolation** — the product never lets
> one workspace see another's data (§10).

---

## 8. CI Gates

CI **MUST** run all of the following; a **red gate blocks merge** (Constitution
§13, §14):

1. **Tests** — full PHPUnit suite (unit + integration + feature).
2. **Static analysis** — PHPStan/Psalm.
3. **Coding standard** — PHP_CodeSniffer (PSR-12).
4. **Dependency audit** — `composer audit`.

```bash
# EXAMPLE ONLY — representative CI gate commands (not the canonical pipeline)
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
composer audit
```

- The same gates run pre-merge on every pull request (trunk-based, short-lived
  branches — Constitution §14).
- A merge **MUST NOT** proceed while any gate is red. Skipping or muting a gate to
  merge is a defect.

---

## 9. Coverage Thresholds

- **Domain-layer logic MUST reach ≥ 80% coverage** (Constitution §13). This is the
  binding floor and is enforced in CI for the Domain layer.
- Coverage is a **floor, not a target**: high coverage of trivial code does not
  substitute for meaningful assertions, especially the security tests in §10.
- Application/Infrastructure/Presentation SHOULD be well covered by integration
  and feature tests, prioritizing behavior over line count.

---

## 10. Testing Multi-Tenant Isolation & Permissions (mandatory)

These two areas are the platform's highest-risk invariants and **MUST** be tested
explicitly, with **negative** tests, in every module that touches them.

### 10.1 Multi-tenant isolation
- For every workspace-scoped repository/use case, include a **cross-tenant
  negative test**: an actor in **Workspace A** attempting to read or modify
  **Workspace B**'s data **MUST** be denied / return nothing.
- Tests MUST assert the tenant guard cannot be bypassed (e.g. supplying another
  workspace's record id within Workspace A's context yields no access).
- Files, AI usage, billing, and search isolation are tested the same way
  (`SECURITY_GUIDE.md` §4).

```php
// EXAMPLE ONLY — cross-tenant negative test; illustrative, not implementation
public function workspace_a_cannot_read_workspace_b_job(): void
{
    $jobB = $this->factory->job(['workspace_id' => $this->workspaceB]);
    $repo = $this->jobs->forWorkspace($this->workspaceA);   // acting as A
    self::assertNull($repo->find($jobB->id));               // B's data invisible to A
}
```

### 10.2 Permission checks
- For every protected action, include both a **positive** test (granted permission
  ⇒ allowed) and a **negative** test (missing permission ⇒ denied), per
  `PERMISSION_MODEL.md` deny-by-default.
- Tests reference **permission keys only** (`job.create`, `candidate.export`,
  `system.workspaces.manage`) and **MUST NOT** assert on role names — code never
  branches on role names, and neither do tests.
- Confirm **UI hiding is not relied upon**: a denied action invoked directly at
  the Application boundary MUST be rejected even when the UI would have hidden it
  (`SECURITY_GUIDE.md` §3).

---

## 11. What Each Phase Must Test

HaHireAI is built docs-first, phase by phase (`MODULES.md` phase column). Testing
follows the **phase acceptance-criteria pattern**: *a phase is not "done" until
its module's tests prove its acceptance criteria, including the mandatory security
tests in §10.*

- Each module's specification in `FEATURE_SPECIFICATIONS/` defines its
  **acceptance criteria**; the module's `Tests/` MUST map to those criteria.
- Representative emphasis by phase (illustrative — the spec is authoritative):

| Phase / area | Must test (in addition to §10 where workspace-scoped) |
|---|---|
| Core Kernel / Database (7–8) | Container resolution, routing/dispatch, migration runner, base repository |
| Authentication / Users / Permissions (8) | Argon2id verify+rehash, session/CSRF, deny-by-default permission checks |
| Workspaces / Memberships (9) | Tenant guard, lifecycle, membership + per-workspace permissions |
| Files / Notifications / Search / Audit (9) | Upload validation, workspace-scoped storage & search, audit emission |
| Recruitment (10) | Domain rules (pipeline transitions, offers), tenant + permission isolation |
| AI Engine (11) | Capability routing, per-workspace usage/limits, no provider leakage |
| Workflow / Integration (12–13) | Triggers/actions, token/scope auth, webhook signature & replay |
| Subscriptions / Billing / Licensing (14) | Per-workspace plan/limits/entitlements, financial isolation |
| Observability (15) | Health probes, metrics/log emission |
| Browser / E2E (**16**) | End-to-end user journeys via browser e2e (introduced this phase) |

> **Browser/E2E tests are introduced at Phase 16** (Constitution alignment). Until
> then, top-of-pyramid coverage is feature tests at the request level; the
> earlier phases MUST NOT block on browser tooling.

---

### Related Documents
`PROJECT_CONSTITUTION.md` (§13) · `ARCHITECTURE.md` · `MODULES.md` ·
`PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` ·
`DEPLOYMENT_GUIDE.md` · `CODING_STANDARD.md` · `FEATURE_SPECIFICATIONS/`
