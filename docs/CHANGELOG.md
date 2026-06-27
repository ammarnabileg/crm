# CHANGELOG — HaHireAI

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> During the pre-implementation phases (1–6) the "changes" are documentation
> artifacts. Code-bearing changes begin at Phase 7 (Core Kernel).

---

## [Unreleased]

### Added — Phase 1: Project Constitution & Architecture Foundation
- `PROJECT_CONSTITUTION.md` — the supreme reference (vision, principles,
  architecture/development rules, naming, folder & module standards, security,
  performance, documentation, testing, release policies, amendment process).
- Canon model docs: `DOMAIN_MODEL.md`, `USER_MODEL.md`, `WORKSPACE_MODEL.md`,
  `PERMISSION_MODEL.md`.
- Canon architecture docs: `ARCHITECTURE.md`, `MODULES.md`.
- Orientation & standards docs: `SYSTEM_OVERVIEW.md`, `DATABASE_GUIDE.md`,
  `APPLICATION_FLOW.md`, `AI_ENGINE.md`, `INSTALLATION.md`, `API_GUIDELINES.md`,
  `CODING_STANDARD.md`, `UI_GUIDELINES.md`, `SECURITY_GUIDE.md`,
  `TESTING_GUIDE.md`, `DEPLOYMENT_GUIDE.md`.
- `docs/README.md` — documentation index, reading order, and status board.
- Established the foundational decisions: native-PHP modular monolith, the
  single `User` identity + `System Owner`, the `Workspace` tenant boundary,
  permission-based authorization (no hard-coded roles), ULID primary keys, and
  AI as a central engine.

### Added — Phase 2: System Blueprint & Module Architecture
- `SYSTEM_BLUEPRINT.md` — the complete engineering map (layers, business
  domains, module boundaries, dependencies, communication rules, shared
  services, expansion strategy).
- `STATE_DIAGRAMS.md` — canonical state machines (Job, Application, Offer,
  Interview, Employee, Membership, Invitation, Subscription, Workspace,
  Workflow execution).
- `NAVIGATION_MAP.md`, `USER_JOURNEYS.md`, `PERMISSION_MATRIX.md`.
- `FEATURE_SPECIFICATIONS/` — 23 per-module specifications plus an index,
  each with Purpose/Scope/Inputs/Outputs/Dependencies/Permissions/Events/
  Data/Acceptance-Criteria.
- Resolved Recruitment as a single bounded context (not fragmented modules).

### Added — Phase 3: Database Architecture & Data Modeling
- `ENTITY_CATALOG.md` — the authoritative list of every entity (~79 tables),
  its tenancy scope (Global vs Workspace), owning module, and relationships.
- `DATABASE_ARCHITECTURE.md` — the data-model design (naming, ULID PKs, FK
  referential actions, constraints, tenancy, migration engine).
- `ER_DIAGRAM.md` (Mermaid, all entities), `RELATIONSHIP_MATRIX.md`,
  `INDEXING_GUIDE.md`.
- `AUDIT_POLICY.md`, `ARCHIVING_POLICY.md`, `VERSIONING_POLICY.md`.
- Confirmed: ULID-only keys (no `AUTO_INCREMENT`), no MySQL `ENUM`, mandatory
  `workspace_id` tenant guard, no duplicated/derived data, acyclic FK ownership.

### Notes
- Repository reset to a clean slate before Phase 1 (previous placeholder README
  removed; recoverable from git history).

---

[Unreleased]: https://github.com/ammarnabileg/crm/tree/claude/vibrant-wright-vv65qw
