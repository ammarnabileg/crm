# 00 — Documentation Index (فهرس التوثيق)

The entry point and single source of truth for HalaOps. This `/docs` folder — not the code, not tribal knowledge — is authoritative; code is written to match these documents.

## Related Documents

- [01-Project-Vision](01-Project-Vision.md)
- [02-Business-Rules](02-Business-Rules.md)
- [05-Database-Architecture](05-Database-Architecture.md)
- [06-ERD](06-ERD.md)
- [45-Future-Roadmap](45-Future-Roadmap.md)
- [CHANGELOG](CHANGELOG.md)

## Purpose (الهدف)

This document explains what the `/docs` folder is, how to use it, the conventions every doc follows, and how all 47 documents fit together. It is the map; the other documents are the territory. Read this first.

## Why It Exists (سبب وجوده)

A production SaaS sold to thousands of companies needs one place where the truth lives. When code and documentation disagree, ambiguity creeps in and bugs follow. HalaOps adopts a **documentation-first** rule: the spec in `/docs` is written or updated *before* the code, and code is then made to conform. This index exists so any contributor can find the relevant spec in seconds and so reviewers can verify code against a known reference.

## Architecture

The documentation set is organized into thematic ranges. Every file follows the same mandatory section template, so once you know the shape of one doc you know them all.

```mermaid
flowchart TD
    R[00-README index] --> V[01-02 Vision and Rules]
    R --> ARCH[03-06 Architecture and Data]
    R --> SEC[07-11 Identity RBAC Tenancy]
    R --> BIZ[12-15 Company Billing]
    R --> AI[16-18 AI]
    R --> JOUR[19-26 Journeys and Domain]
    R --> SUP[27-31 Supporting and App tiers]
    R --> OPS[32-38 Ops Security Observability]
    R --> QUAL[39-44 Quality and Deployment]
    R --> FWD[45 Roadmap + CHANGELOG]
```

### How `/docs` is the single source of truth

1. **Documentation-first development** — no feature is built until its behavior is specified here. The canonical context and these docs define the stack, schema, RBAC, tenancy, and conventions.
2. **No contradictions** — facts (table names, columns, permission keys, statuses) appear once and are referenced elsewhere. The cross-reference map below keeps docs linked rather than duplicated.
3. **Cite, don't restate** — business rules carry stable IDs (`BR-001…`) in [02-Business-Rules](02-Business-Rules.md); other docs cite the ID.
4. **Code conforms to docs** — when code and a doc disagree, the doc wins; either the code is fixed or the doc is consciously updated (and recorded in [CHANGELOG](CHANGELOG.md)).

## Workflow

The lifecycle of any change to HalaOps:

```mermaid
sequenceDiagram
    participant Dev
    participant Docs as /docs
    participant Code
    participant Review
    Dev->>Docs: 1. Write/Update spec (BR ids, schema, sections)
    Docs-->>Dev: Reviewed spec is the contract
    Dev->>Code: 2. Implement to match the spec
    Dev->>Review: 3. PR cites the docs it satisfies
    Review->>Docs: 4. Verify code conforms to /docs
    Review->>Code: Approve / request changes
    Dev->>Docs: 5. Update CHANGELOG (Unreleased)
```

### Reading order (recommended)

1. [01-Project-Vision](01-Project-Vision.md) — why we build this and for whom.
2. [02-Business-Rules](02-Business-Rules.md) — the enumerated invariants everything cites.
3. [03-System-Architecture](03-System-Architecture.md) → [06-ERD](06-ERD.md) — how the system and data are shaped.
4. [07-RBAC](07-RBAC.md) → [11-Permissions-Matrix](11-Permissions-Matrix.md) — identity, tenancy, authorization.
5. [12-Company-Management](12-Company-Management.md) → [18-AI-Interview-Engine](18-AI-Interview-Engine.md) — companies, billing, AI.
6. [19-Candidate-Journey](19-Candidate-Journey.md) → [26-Notification-System](26-Notification-System.md) — journeys and recruitment domain.
7. [27-Storage-System](27-Storage-System.md) → [38-Audit-System](38-Audit-System.md) — supporting systems, app tiers, ops.
8. [39-Testing-Strategy](39-Testing-Strategy.md) → [45-Future-Roadmap](45-Future-Roadmap.md) and [CHANGELOG](CHANGELOG.md) — quality, deployment, roadmap.

## Business Rules

The documentation process itself follows a few rules (the platform's product/business rules live in [02-Business-Rules](02-Business-Rules.md)):

- **DOC-R1** Every doc begins with an H1 title and a one-line summary, then a "Related Documents" list, then the 14 mandatory H2 sections (see Conventions).
- **DOC-R2** Every section contains real, specific, HalaOps-accurate content — no placeholders, no "TODO", no "coming soon", no empty headings.
- **DOC-R3** Facts are stated once and referenced; table/column/permission names match the canonical schema exactly.
- **DOC-R4** Business rules are cited by their `BR-xxx` ID, never paraphrased into a new rule.
- **DOC-R5** Diagrams use Mermaid; Arabic terms appear in parentheses where the spec used them.

### Documentation conventions

Mandatory section template for every doc (H2 headings, in order): Purpose, Why It Exists, Architecture, Workflow, Business Rules, Database Relations, Permissions, Validation, Edge Cases, Security, Performance, Testing, Future Expansion, Open Questions. Files are typically 150–400+ lines and reference real paths under `/home/user/crm` (e.g. `app/Core/Model.php`, `config/rbac.php`, `database/migrations/0010_create_subscriptions_table.php`).

### The complete document set (47 documents)

| # | Document | One-line description |
|---|----------|----------------------|
| 00 | [00-README](00-README.md) | This index; how to use `/docs` as the single source of truth |
| 01 | [01-Project-Vision](01-Project-Vision.md) | Vision, mission, market, personas, success metrics, non-goals |
| 02 | [02-Business-Rules](02-Business-Rules.md) | Enumerated platform rules (BR-001…) cited everywhere |
| 03 | [03-System-Architecture](03-System-Architecture.md) | High-level architecture of the pure-PHP micro-framework |
| 04 | [04-Folder-Structure](04-Folder-Structure.md) | Repository layout and what each directory contains |
| 05 | [05-Database-Architecture](05-Database-Architecture.md) | Schema design, conventions, constraints, tenancy columns |
| 06 | [06-ERD](06-ERD.md) | Entity-relationship diagram of built and planned tables |
| 07 | [07-RBAC](07-RBAC.md) | Roles, permissions, inheritance, policy gates |
| 08 | [08-Multi-Tenant](08-Multi-Tenant.md) | Row-level tenant isolation, fail-closed scoping, trade-offs |
| 09 | [09-Authentication](09-Authentication.md) | Login, register, password reset, sessions, throttling |
| 10 | [10-Authorization](10-Authorization.md) | Permission checks, policy gates, middleware enforcement |
| 11 | [11-Permissions-Matrix](11-Permissions-Matrix.md) | Role × permission matrix for every default role |
| 12 | [12-Company-Management](12-Company-Management.md) | Company creation, provisioning, membership management |
| 13 | [13-Subscription-System](13-Subscription-System.md) | Data-driven plans and subscription lifecycle |
| 14 | [14-Billing-System](14-Billing-System.md) | Invoices, payments, payment methods |
| 15 | [15-Payment-Gateways](15-Payment-Gateways.md) | Pluggable gateways (Moyasar, Tap, HyperPay…) and webhooks |
| 16 | [16-AI-Architecture](16-AI-Architecture.md) | Bring-your-own-keys AI layer overview |
| 17 | [17-AI-Providers](17-AI-Providers.md) | Provider interface, registry, supported providers |
| 18 | [18-AI-Interview-Engine](18-AI-Interview-Engine.md) | Question generation, conducting, scoring, analysis |
| 19 | [19-Candidate-Journey](19-Candidate-Journey.md) | The candidate's experience end to end |
| 20 | [20-Recruiter-Journey](20-Recruiter-Journey.md) | The recruiter's day-to-day workflow |
| 21 | [21-HR-Journey](21-HR-Journey.md) | The HR manager's hiring-process ownership |
| 22 | [22-SuperAdmin-Journey](22-SuperAdmin-Journey.md) | Platform staff operating across tenants |
| 23 | [23-Interview-Workflow](23-Interview-Workflow.md) | Scheduling, conducting, scoring interviews |
| 24 | [24-Job-Lifecycle](24-Job-Lifecycle.md) | Job from draft through open to closed/archived |
| 25 | [25-Application-Lifecycle](25-Application-Lifecycle.md) | Application through pipeline stages to decision |
| 26 | [26-Notification-System](26-Notification-System.md) | In-app and email notifications, preferences |
| 27 | [27-Storage-System](27-Storage-System.md) | Files table, disk abstraction, validation, visibility |
| 28 | [28-Search-System](28-Search-System.md) | Tenant-filtered FULLTEXT search behind an abstraction |
| 29 | [29-API-Architecture](29-API-Architecture.md) | Token-authenticated, versioned REST API |
| 30 | [30-Frontend-Architecture](30-Frontend-Architecture.md) | Server-rendered templates, Tailwind, vanilla JS |
| 31 | [31-Backend-Architecture](31-Backend-Architecture.md) | Core framework: router, container, models, services |
| 32 | [32-Setup-Installer](32-Setup-Installer.md) | Browser installer with live console and recovery |
| 33 | [33-System-Diagnostics](33-System-Diagnostics.md) | Health checks and environment reporting |
| 34 | [34-Security](34-Security.md) | Platform-wide security model and mitigations |
| 35 | [35-Performance](35-Performance.md) | Indexes, caching, pagination, hot paths |
| 36 | [36-Scalability](36-Scalability.md) | Horizontal scale, replicas, tenant sharding path |
| 37 | [37-Logging](37-Logging.md) | Leveled file logging and request/error logs |
| 38 | [38-Audit-System](38-Audit-System.md) | `activity_log` audit trail of security/business events |
| 39 | [39-Testing-Strategy](39-Testing-Strategy.md) | Unit, feature, and security testing approach |
| 40 | [40-QA-Checklist](40-QA-Checklist.md) | Per-feature manual QA checklist |
| 41 | [41-Coding-Standards](41-Coding-Standards.md) | PSR-12, strict types, conventions |
| 42 | [42-Code-Review-Checklist](42-Code-Review-Checklist.md) | What reviewers verify on every PR |
| 43 | [43-Deployment](43-Deployment.md) | Shipping and installing the package |
| 44 | [44-Production-Checklist](44-Production-Checklist.md) | Go-live readiness checklist |
| 45 | [45-Future-Roadmap](45-Future-Roadmap.md) | Phased roadmap and 5-year scalability outlook |
| 46 | [46-Architecture-Review](46-Architecture-Review.md) | Independent self-audit: verification results, strengths, weaknesses, gaps, risks, 5-year outlook |
| — | [CHANGELOG](CHANGELOG.md) | Versioned record of changes (Keep a Changelog) |

That is 47 numbered documents (00–46) plus the CHANGELOG. The set covers the
originally specified 00–45 plus the architecture-review / self-audit report.

## Database Relations

This index does not own data, but it summarizes the schema so newcomers have orientation before diving into [05-Database-Architecture](05-Database-Architecture.md) and [06-ERD](06-ERD.md).

The schema is split into **built** tables (migrations `0001`–`0015` plus `migrations`) and **planned** domain tables documented as planned:

```mermaid
erDiagram
    USERS ||--o{ MEMBERSHIPS : has
    COMPANIES ||--o{ MEMBERSHIPS : has
    COMPANIES ||--o{ ROLES : "tenant roles"
    ROLES ||--o{ PERMISSION_ROLE : grants
    PERMISSIONS ||--o{ PERMISSION_ROLE : in
    MEMBERSHIPS ||--o{ MEMBERSHIP_ROLE : assigned
    USERS ||--o{ USER_ROLE : "global roles"
    COMPANIES ||--o{ SUBSCRIPTIONS : pays
    PLANS ||--o{ SUBSCRIPTIONS : priced_by
    COMPANIES ||--o{ AI_CREDENTIALS : owns
    COMPANIES ||--o{ JOBS : posts
    JOBS ||--o{ APPLICATIONS : receives
    USERS ||--o{ APPLICATIONS : "as candidate"
    APPLICATIONS ||--o{ INTERVIEWS : leads_to
    INTERVIEWS ||--o{ EVALUATIONS : produce
```

- **Built / identity & tenancy**: `users`, `companies`, `memberships`, `roles`, `permissions`, `permission_role`, `membership_role`, `user_role`.
- **Built / commercial & config**: `plans`, `subscriptions`, `ai_credentials`, `settings`, `onboarding_progress`.
- **Built / infra & audit**: `password_resets`, `activity_log`, `migrations`.
- **Planned / recruitment**: `jobs`, `pipeline_stages`, `applications`, `application_events`, `interviews`, `interview_participants`, `interview_questions`, `interview_responses`, `ai_interview_sessions`, `evaluations`.
- **Planned / supporting**: `files`, `notifications`, `notification_preferences`, `invoices`, `payments`, `payment_methods`, `gateway_events`, `api_tokens`, `queued_jobs`, `failed_jobs`.

Every tenant table carries a `company_id` FK and index; uniqueness is per-company where relevant. See [05-Database-Architecture](05-Database-Architecture.md) for the authoritative definitions.

## Permissions

This index is for everyone and gates nothing, but it points to where authorization is specified:

- The permission catalogue and default roles are defined in [07-RBAC](07-RBAC.md) (data-driven in `config/rbac.php`).
- The role × permission matrix is in [11-Permissions-Matrix](11-Permissions-Matrix.md).
- Built permission groups today: `dashboard.*`, `company.*`, `members.*`, `roles.*`, `billing.*`, `ai.*`, `settings.*`. Planned groups (jobs, applications, interviews, evaluations, candidate, notifications, files, platform) are added as their modules ship.

## Validation

To keep the docs trustworthy, every document is validated against:

- Presence of the H1 + one-line summary, Related Documents, and all 14 mandatory sections (DOC-R1).
- No placeholders/TODO/empty sections (DOC-R2).
- Table, column, permission, and status names that match the canonical schema exactly (DOC-R3).
- Correct cross-reference links per the map below (DOC-R4/DOC-R5).
- Valid Mermaid syntax in diagrams.

## Edge Cases

- **A doc references a fact not yet in the canonical context** — the author infers it consistently and records it under that doc's "Open Questions"; it is reconciled into the canonical context later.
- **Two docs appear to define the same rule** — that is a defect; consolidate into [02-Business-Rules](02-Business-Rules.md) and reference it.
- **A module ships before its doc** — not allowed under documentation-first; the doc is written first (DOC-R-process).
- **A planned table changes shape during implementation** — update [05-Database-Architecture](05-Database-Architecture.md)/[06-ERD](06-ERD.md) and note it in [CHANGELOG](CHANGELOG.md).

## Security

- The docs themselves describe security but must not contain secrets — no real API keys, passwords, connection strings, or customer data ever appear in `/docs`.
- Security-relevant behavior is specified centrally in [34-Security](34-Security.md) and cited, so a single review surface covers it.
- Examples use placeholders (e.g. `base64:...` for `APP_KEY`) that are obviously non-functional.

## Performance

- The index is a static Markdown file; it has no runtime cost.
- Keeping facts centralized (cite-don't-restate) reduces the maintenance "read amplification" when something changes — one edit instead of many.
- The cross-reference map lets tooling build a link graph to detect orphaned or broken references quickly.

## Testing

- A docs linter (CI) checks: every numbered file 00–45 plus CHANGELOG exists; each has the mandatory sections; internal links resolve; Mermaid blocks parse.
- A consistency check verifies that table/column/permission names used in docs exist in the canonical schema.
- A "no placeholder" check fails the build on `TODO`, `coming soon`, or empty sections.

## Future Expansion

- New documents are appended with the next number and added to the table above and the cross-reference map.
- If a document grows too large, it is split (e.g. a domain area) and the parent links to the children.
- The roadmap in [45-Future-Roadmap](45-Future-Roadmap.md) and the [CHANGELOG](CHANGELOG.md) together track how the spec evolves over time.

## Open Questions

- None at this time. The document set, conventions, and cross-reference map are fully specified above; open product/technical questions live in their respective documents' "Open Questions" sections.
