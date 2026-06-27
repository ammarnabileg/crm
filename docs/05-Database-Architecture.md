# 05 — Database Architecture

The authoritative description of how HalaOps stores, isolates, and evolves its data: storage engine choices, naming conventions, the migration system, seeders, indexing strategy, and the complete catalogue of every table (built and planned) classified as GLOBAL or TENANT-scoped.

## Related Documents

- [06 — Entity-Relationship Diagram (ERD)](06-ERD.md) — the table-by-table schema and the diagram approved before migrations.
- [08 — Multi-Tenant Architecture](08-Multi-Tenant.md) — the row-level isolation model these tables implement.
- [34 — Security](34-Security.md) — encryption, prepared statements, and data-protection rules referenced here.
- [35 — Performance](35-Performance.md) — query tuning, caching, and the indexing strategy in context.
- [36 — Scalability](36-Scalability.md) — read replicas, tenant sharding, and how this schema evolves.

---

## Purpose (الهدف)

This document defines the **database layer** of HalaOps: the physical conventions every table follows (engine, charset, primary keys, foreign-key behavior), the **migration system** that creates and versions the schema, the **seeders** that populate baseline data, the **indexing strategy** that keeps tenant-filtered queries fast, and a **categorized inventory of all 36 tables** (16 built, 20 planned) that make up the platform. It is the canonical reference any engineer reads before adding a column, writing a migration, or reasoning about data isolation.

## Why It Exists (سبب وجوده)

HalaOps is a multi-tenant SaaS sold to thousands of companies and deployed on **cheap shared MySQL/MariaDB hosting** with **no CLI access** for the buyer. That constraint shapes every database decision:

- We use **one shared database with row-level tenant isolation** (a `workspace_id` on every tenant table) rather than a database-per-tenant, because shared hosting cannot provision databases on demand and most buyers get exactly one database.
- The schema must be created and upgraded **entirely from the browser installer** (`/setup`) — there is no `php artisan migrate` for the buyer — so the migration system has to be self-contained, idempotent at the table level, and report progress to a live console.
- Tenant isolation must be enforced **at the data layer and fail closed**, so the column conventions (a `workspace_id` FK on every tenant table) are not optional decoration; they are the substrate the `Model` tenant scope relies on.
- The business model is **data-driven** (unlimited plans, roles, permissions, AI providers without code changes), so several tables intentionally carry JSON columns and per-workspace catalogues.

Without a single written contract for these conventions, two engineers would pick different FK behaviors, forget the `workspace_id`, or break the installer. This document is that contract.

## Architecture

The database layer is built from a small number of cooperating pieces, all under `database/` and `app/Core/`:

| Component | File | Responsibility |
|-----------|------|----------------|
| `Database` | `app/Core/Database.php` | A single PDO connection (`ERRMODE_EXCEPTION`, real prepares, `utf8mb4`), prepared statements only, nested transactions via SAVEPOINTs, and `unprepared()` for DDL. |
| `QueryBuilder` | `app/Core/QueryBuilder.php` | Fluent, fully parameterized SQL with backtick-quoted identifiers. The only sanctioned way to read/write rows. |
| `Model` | `app/Core/Model.php` | Active-record base. Adds the tenant scope (`$tenantScoped`, `$tenantColumn = 'workspace_id'`), `$fillable`, `$hidden`, `$casts`, and timestamps. |
| `Migration` | `database/Migration.php` | Abstract base every migration extends; `up(Database)` / `down(Database)`. |
| `Migrator` | `database/Migrator.php` | Discovers `database/migrations/NNNN_*.php`, tracks applied ones in `migrations`, runs pending ones in order, supports rollback of the last batch. |
| `DatabaseSeeder` | `database/seeders/DatabaseSeeder.php` | Idempotently seeds the permission catalogue, the super-admin role, and the first plan. |

**Layering.** Application code never writes SQL by hand. It goes Model → QueryBuilder → Database → PDO. Migrations are the **only** code allowed to issue DDL, and they do it through `Database::unprepared()` with explicit SQL strings (no schema-builder abstraction) so the generated DDL is exact and reviewable.

### Design principles

1. **Engine: InnoDB, always.** Every `CREATE TABLE` ends with `ENGINE=InnoDB`. We rely on real foreign-key constraints, row-level locking, and crash recovery. MyISAM is never used.
2. **Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`.** Set at the connection level (`config/database.php`) and on every table. `utf8mb4` is mandatory for full Unicode — the product is bilingual Arabic/English and must store emoji, names, and RTL text losslessly. `utf8mb4_unicode_ci` gives language-correct, case-insensitive comparisons.
3. **Primary keys: `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.** A SaaS selling to thousands of tenants will exceed 32-bit ranges in busy tables (applications, activity_logs, notifications); `BIGINT UNSIGNED` is the default everywhere. Two intentional exceptions: pure pivot tables use a **composite PK** (e.g. `role_permissions (role_id, permission_id)`), and `password_resets` uses `email` as its PK.
4. **Foreign keys are real and explicit, with a deliberate ON DELETE strategy:**
   - **CASCADE** for owned child rows whose existence is meaningless without the parent — e.g. `memberships → workspaces`, `role_permissions → roles`, `settings → workspaces`. Deleting the parent removes the children.
   - **SET NULL** for *optional* references where the row should survive the loss of the pointer — e.g. `memberships.invited_by → users`, `activity_logs.user_id → users`, `roles.parent_id → roles`. The history/record remains; the link becomes NULL.
   - **RESTRICT** where deletion must be *blocked* to protect integrity — e.g. `workspaces.owner_id → users` (you cannot delete a user who still owns a workspace) and `subscriptions.plan_id → plans` (you cannot delete a plan that still has subscriptions). `ON UPDATE CASCADE` is set on all FKs.
5. **Per-workspace uniqueness.** Uniqueness on tenant tables is scoped to the tenant via composite unique keys, never a bare global unique: `roles (workspace_id, slug)`, `settings (workspace_id, key)`, `memberships (workspace_id, user_id)`, `tenant_ai_keys (workspace_id, provider)`. Two different workspaces may each have a role slugged `recruiter`. Truly global tables keep simple unique keys (`users.email`, `workspaces.slug`, `plans.slug`, `permissions.key`).
6. **Timestamps.** Tenant and entity tables carry `created_at` / `updated_at TIMESTAMP NULL DEFAULT NULL`, written by the `Model` (`$timestamps = true`). Append-only logs (`activity_logs`, `files`, `notifications`, `gateway_events`) carry only `created_at`. `password_resets.created_at` defaults to `CURRENT_TIMESTAMP` because it is written outside the Model.
7. **JSON columns for extensibility.** Where the spec promises "extensible without code changes," we use native `JSON`: `workspaces.settings`, `plans.features`, `plans.limits`, `tenant_ai_keys.meta`, `onboarding_progress.completed_steps`, `activity_logs.properties`, and the planned `*.data` / `*.criteria` / `*.line_items` columns. JSON is for sparse, schema-flexible data — never for fields we filter or join on (those get real columns).
8. **No hard-coded enums — status/type sets are configuration-driven.** Lifecycle and status fields are **not** `ENUM(...)`; each is a `<entity>_status_id` FK to a per-entity status table (`subscriptions.subscription_status_id → subscription_statuses`, `workspaces.workspace_status_id → workspace_statuses`) or, for simple lists, a FK into `lookup_values` (`users.user_status_id`, `memberships.membership_status_id`, `plans.interval_id → lookup_values[billing_interval]`). Invalid states are rejected by the FK, and tenants can add their own statuses without a schema change.
9. **Money.** Always `DECIMAL(10,2)` with a separate `currency VARCHAR(3)` (default `SAR`). Never floats.

### Naming conventions

These are enforced in review and visible across `database/migrations/`:

- **Tables**: lowercase `snake_case`, **plural** for entities (`users`, `workspaces`, `subscriptions`) and **descriptive plural** for pivots (`role_permissions`, `membership_roles`, `user_roles`). Log tables are plural too (`activity_logs`).
- **Columns**: `snake_case`. Foreign keys are `<singular_referenced>_id` (`workspace_id`, `user_id`, `plan_id`, `parent_id`, `invited_by` is the one semantic exception); status FKs are `<entity>_status_id`.
- **Constraints/indexes** follow Laravel-style auto-names so they are predictable: unique `<table>_<col>_unique`, index `<table>_<col>_index`, foreign `<table>_<col>_foreign`, composite uniques use a descriptive middle (`memberships_workspace_user_unique`, `roles_workspace_slug_unique`).
- **Migrations**: `NNNN_verb_subject.php` zero-padded, sorted lexicographically (`0001_create_users_table.php` … `0015_create_activity_log_table.php`). Planned domain migrations continue the sequence (`0016_…` upward).
- **Booleans**: `TINYINT(1)` with `is_` / `has_` prefixes (`is_system`, `is_active`, `is_default`, `is_public`, `is_completed`), cast to PHP `bool` in models.

## Workflow

### How the schema is created (install time)

```mermaid
sequenceDiagram
    participant Buyer as Buyer (browser)
    participant Inst as /install (InstallManager)
    participant Mig as Migrator
    participant DB as MySQL
    participant Seed as DatabaseSeeder

    Buyer->>Inst: Step "Database" (host/db/user/pass)
    Inst->>DB: Test connection (PDO, utf8mb4)
    Buyer->>Inst: Step "Migrate"
    Inst->>Mig: run(report callback)
    Mig->>DB: CREATE TABLE migrations IF NOT EXISTS
    loop each pending NNNN_*.php in order
        Mig->>DB: require file; $migration->up($db) (DDL, no transaction)
        Mig->>DB: INSERT INTO migrations (migration, batch, executed_at)
        Mig-->>Inst: report(name, ok|fail, error?)
        Inst-->>Buyer: live console line
    end
    Buyer->>Inst: Step "Seed"
    Inst->>Seed: run($db)
    Seed->>DB: sync permissions, ensure super-admin role, seed Standard plan
    Inst-->>Buyer: success → write .env + lock file
```

### Adding a new table or column (development time)

1. Update the **ERD** ([06-ERD.md](06-ERD.md)) first — the ERD is the artifact that must be **approved before any migration is written**.
2. Create `database/migrations/NNNN_*.php` extending `Database\Migration`, writing explicit SQL in `up()` and the inverse `DROP`/`ALTER` in `down()`.
3. Add or update the matching `App\Models\*` model: set `$table`, `$tenantScoped` (true for tenant tables), `$fillable`, `$hidden`, `$casts`.
4. If the table holds catalogue/baseline data, extend a seeder idempotently.
5. Run via the installer locally; the `Migrator` records the migration in `migrations`.

### Rollback

`Migrator::rollback()` finds the highest `batch`, runs each migration's `down()` in reverse `id` order, and deletes its `migrations` row. Because MySQL auto-commits DDL, a rollback is best-effort per statement, not a single atomic transaction.

## Business Rules

1. **One database, shared by all tenants.** There is no per-tenant database or schema. Isolation is by `workspace_id`.
2. **Every tenant-scoped table MUST have `workspace_id BIGINT UNSIGNED`** with an FK to `workspaces` and an index. A tenant table without `workspace_id` is a bug.
3. **`workspaces` cannot be tenant-scoped** — it *is* the tenant root; scoping it to itself is nonsensical. Its model sets `$tenantScoped = false`.
4. **GLOBAL tables** (`users`, `workspaces`, `permissions`, `plans`, `password_resets`, `roles` when `workspace_id IS NULL`, `migrations`) hold platform-wide data and are never auto-filtered by tenant.
5. **Cross-tenant reads are forbidden in normal code paths.** The only sanctioned escape is `Model::withoutTenantScope()`, reserved for platform/super-admin and system jobs (see [08-Multi-Tenant.md](08-Multi-Tenant.md)).
6. **Foreign-key behavior is intentional, not incidental.** CASCADE, SET NULL, and RESTRICT are chosen per relationship per the rules in *Design principles* and must match the ERD.
7. **Money is `DECIMAL(10,2)` + currency**; lifecycle/status is a config-driven `<entity>_status_id` FK (never a hard-coded `ENUM`); secrets are encrypted before insert (never stored plaintext).
8. **Migrations are immutable once shipped.** A released migration is never edited; corrections ship as a new numbered migration.
9. **Seeders are idempotent.** Re-running the seeder (installer recovery) must not duplicate rows — every seed checks existence first (e.g. `plans.slug = 'standard'`).

## Database Relations

This section catalogues **all 36 tables**, classified and described. "Scope" is **GLOBAL** (platform-wide) or **TENANT** (carries `workspace_id`). Full column lists, types, and index/FK detail live in [06-ERD.md](06-ERD.md); this is the architectural inventory.

### Built tables (migrations 0001–0015 + `migrations`)

| # | Table | Scope | Purpose | Key relations |
|---|-------|-------|---------|---------------|
| 1 | `users` | GLOBAL | The single identity table for everyone (candidates, staff, super admins). No user-type column. | Referenced by nearly every table. |
| 2 | `workspaces` | GLOBAL (tenant root) | The tenant itself. | `owner_id → users` (RESTRICT), `workspace_type_id → workspace_types` (RESTRICT), `workspace_status_id → workspace_statuses` (RESTRICT). |
| 3 | `memberships` | TENANT | Links a user to a workspace; the per-workspace "seat". | `workspace_id → workspaces` (CASCADE), `user_id → users` (CASCADE), `invited_by → users` (SET NULL). UQ `(workspace_id, user_id)`. |
| 4 | `roles` | GLOBAL when `workspace_id IS NULL`, else TENANT | RBAC roles with single-parent inheritance. | `workspace_id → workspaces` (CASCADE), `parent_id → roles` (SET NULL). UQ `(workspace_id, slug)`. |
| 5 | `permissions` | GLOBAL | The permission catalogue, referenced by `key`. | `module_id → system_modules` (RESTRICT). Linked to roles via `role_permissions`. |
| 6 | `role_permissions` | GLOBAL | Which permissions a role grants. | PK `(role_id, permission_id)`, both CASCADE. |
| 7 | `membership_roles` | TENANT (via membership) | Tenant roles held on a membership. | PK `(membership_id, role_id)`, both CASCADE. |
| 8 | `user_roles` | GLOBAL | Global roles granted directly to a user (e.g. super-admin). | PK `(user_id, role_id)`, both CASCADE. |
| 9 | `plans` | GLOBAL | Data-driven subscription plans; features/limits as JSON. | Referenced by `subscriptions`. |
| 10 | `subscriptions` | TENANT | A workspace's subscription lifecycle. | `workspace_id → workspaces` (CASCADE), `plan_id → plans` (RESTRICT). |
| 11 | `tenant_ai_keys` | TENANT | Per-tenant encrypted AI provider keys (built as `ai_credentials`). | `workspace_id → workspaces` (CASCADE). UQ `(workspace_id, provider)`. |
| 12 | `password_resets` | GLOBAL | Hashed, expiring reset tokens (PK `email`). | None (keyed by email). |
| 13 | `settings` | TENANT | Per-workspace key/value settings. | `workspace_id → workspaces` (CASCADE). UQ `(workspace_id, key)`. |
| 14 | `onboarding_progress` | TENANT (nullable workspace) | Per-user onboarding flow progress. | `user_id → users` (CASCADE), `workspace_id → workspaces` (CASCADE, nullable). UQ `(user_id, workspace_id, flow)`. |
| 15 | `activity_logs` | TENANT (nullable workspace) | Audit trail of security/business events (built as `activity_log`). | `workspace_id → workspaces` (CASCADE, nullable), `user_id → users` (SET NULL). |
| 16 | `migrations` | GLOBAL (system) | Tracks applied migrations and batches. | None. UQ `migration`. |

### Planned tables (created when each module ships — 17–36)

**Recruitment domain (tenant-scoped):**

| # | Table | Scope | Purpose |
|---|-------|-------|---------|
| 17 | `jobs` | TENANT | Job postings (title, status draft→open→closed, openings, salary range). UQ `(workspace_id, slug)`. |
| 18 | `pipeline_stages` | TENANT | Hiring pipeline stages, per job or workspace-default template (`job_id` nullable). |
| 19 | `applications` | TENANT | A candidate (user) applying to a job; current stage and status. UQ `(workspace_id, job_id, user_id)`. |
| 20 | `application_events` | TENANT | Append-only timeline of an application (stage moves, notes). |
| 21 | `interviews` | TENANT | AI/human/panel interviews tied to an application + job. |
| 22 | `interview_participants` | TENANT | Interviewers/observers/candidate on an interview. UQ `(interview_id, user_id)`. |
| 23 | `interview_questions` | TENANT | Questions per interview or as templates (`interview_id` nullable). |
| 24 | `interview_responses` | TENANT | Candidate responses with optional AI score/feedback. |
| 25 | `ai_interview_sessions` | TENANT | An AI interview run: transcript, analysis, score, tokens used. |
| 26 | `evaluations` | TENANT | Human scorecards/recommendations per application/interview. |

**Platform / supporting systems:**

| # | Table | Scope | Purpose |
|---|-------|-------|---------|
| 27 | `files` | TENANT | Stored file/media metadata (disk, path, mime, size, checksum, visibility). See [27-Storage-System.md](27-Storage-System.md). |
| 28 | `notifications` | TENANT (nullable workspace) | Per-user in-app/email notifications. |
| 29 | `notification_preferences` | TENANT (nullable workspace) | Per-user channel preferences per type. UQ `(user_id, workspace_id, type, channel)`. |
| 30 | `invoices` | TENANT | Billing invoices with line items (JSON). UQ `number`. |
| 31 | `payments` | TENANT | Gateway payment records. |
| 32 | `payment_methods` | TENANT | Saved (tokenized) payment methods. |
| 33 | `gateway_events` | GLOBAL (system log) | Raw payment-gateway webhook log for idempotency. |
| 34 | `api_tokens` | GLOBAL (nullable workspace) | Hashed REST API tokens with abilities. UQ `token_hash`. |
| 35 | `queued_jobs` | GLOBAL (system) | DB-backed queue for async work (AI, email). |
| 36 | `failed_jobs` | GLOBAL (system) | Dead-letter store for failed queue jobs. UQ `uuid`. |

### GLOBAL vs TENANT summary

- **GLOBAL** (no `workspace_id`, never tenant-filtered): `users`, `workspaces`, `permissions`, `role_permissions`, `user_roles`, `plans`, `password_resets`, `migrations`, `gateway_events`, `queued_jobs`, `failed_jobs`, and `roles`/`api_tokens`/`notifications`/`notification_preferences`/`onboarding_progress` when their `workspace_id` is NULL.
- **TENANT** (carry `workspace_id`, auto-filtered by the Model scope): `memberships`, `membership_roles` (via membership), `subscriptions`, `tenant_ai_keys`, `settings`, `activity_logs` (when workspace set), plus all recruitment tables (`jobs`–`evaluations`), `files`, `invoices`, `payments`, `payment_methods`.

## Permissions

The database layer itself is gated indirectly, through the features that touch it:

- **Schema changes (migrate/seed)** happen only during installation, which is protected by the installer lock file, and via super-admin diagnostics (`platform.diagnostics`). There is no end-user route that runs DDL.
- **Reading/writing tenant rows** is always subject to RBAC at the controller/service layer: `workspace.view`/`workspace.update`, `members.*`, `roles.view`/`roles.manage`, `billing.view`/`billing.manage`, `ai.view`/`ai.manage`, `settings.view`/`settings.manage`, `system.manage` for built tables; the planned `jobs.*`, `applications.*`, `interviews.*`, `evaluations.*`, `files.*`, `notifications.view`, and `platform.*` groups for planned tables (see [07-RBAC.md](07-RBAC.md) and 11-Permissions-Matrix).
- **Cross-tenant data access** (`withoutTenantScope()`) is effectively a super-admin capability and is paired with `platform.*` permissions in code.

## Validation

Validation happens in two layers — application (`App\Core\Validator`) and the database (constraints):

- **Application layer**: every write goes through the `Validator` with rule strings (`required`, `email`, `min`, `max`, `unique:table,column`, `exists:table,column`, `in:...`, `regex`). For example a new workspace validates `slug` `unique:workspaces,slug` and a member invite validates `email` `exists`/`unique` as appropriate.
- **Database layer (defense in depth)**:
  - `NOT NULL` on required columns; `ENUM` rejects invalid statuses.
  - **Unique keys** reject duplicates even under race conditions (e.g. two concurrent invites of the same user → `memberships_company_user_unique` blocks the second).
  - **Foreign keys** reject orphan inserts (you cannot create a `subscription` for a non-existent `plan_id`).
  - **Composite per-company uniques** guarantee tenant-local uniqueness without forcing global uniqueness.
- **JSON columns** are validated in PHP before insert (shape/whitelist of keys); MySQL only guarantees well-formed JSON, not its contents.
- **Length limits** are deliberate: `users.email VARCHAR(190)` (index-safe under utf8mb4 with the 3072-byte InnoDB limit), `slug VARCHAR(160)`, etc.

## Edge Cases

1. **DDL auto-commit.** MySQL implicitly commits on `CREATE`/`ALTER`/`DROP`, so migrations **do not run inside a transaction**. The `Migrator` therefore runs each migration, immediately records it in `migrations`, and on failure **aborts the remaining** migrations and reports the precise failing file. Recovery = fix and re-run (the `migrations` row marks already-applied steps so they are skipped).
2. **Partial migration failure.** If `0019` fails after `0001–0018` applied, the database is left at step 18; the installer's resume-from-last-step re-runs only pending migrations.
3. **Deleting a workspace.** CASCADE removes its `memberships`, tenant `roles`, `settings`, `subscriptions`, `tenant_ai_keys`, and (when added) its recruitment data — by design. The workspace's `users` survive (they are global) but lose that membership.
4. **Deleting a user who owns a workspace.** RESTRICT blocks it; the owner must be transferred first. This prevents orphaned tenants.
5. **Deleting a plan with subscribers.** RESTRICT blocks it; deactivate (`is_active = 0`) instead so existing subscriptions keep their snapshotted `amount`/`currency`.
6. **NULL `workspace_id` rows** (`activity_logs`, `onboarding_progress`, `notifications`) represent platform-level records; tenant-scoped queries must not assume `workspace_id` is always set on these.
7. **utf8mb4 index length.** Indexed string columns are kept ≤191 chars where needed so a single-column unique index stays within InnoDB's byte limit.
8. **Re-seeding.** Running the seeder twice must be safe; each seed guards on existence (the `plans` seed checks `slug = 'standard'`; `RbacManager::syncPermissions()` and `ensureSuperAdminRole()` are idempotent upserts).

## Security

- **Prepared statements only.** All reads/writes go through `QueryBuilder`, which fully parameterizes values and backtick-quotes identifiers; raw string interpolation of user input into SQL is forbidden (see [34-Security.md](34-Security.md)).
- **Encryption at rest for secrets.** `tenant_ai_keys.credentials` stores an AES-256-GCM (authenticated) ciphertext via `encrypt_value()`/`decrypt_value()`; plaintext keys never touch the database. The planned `payment_methods` stores only gateway **tokens**, never PANs.
- **Hashing.** `users.password` is Argon2id (bcrypt fallback) — never reversible. `password_resets.token` and the planned `api_tokens.token_hash` store **hashes**, not the raw token.
- **Tenant isolation is a security control.** The `workspace_id` columns and the fail-closed Model scope prevent cross-tenant data leakage; FK + index on `workspace_id` are mandatory.
- **Least privilege on the DB account.** The application's MySQL user needs DML + the DDL used by the installer; production deployments should restrict it further once installed (see [43-Deployment.md] and [44-Production-Checklist.md]).
- **Audit.** `activity_logs` records security-relevant changes (role grants, AI key updates, ownership changes) with actor, subject, IP, and a JSON property bag (see [38-Audit-System.md]).

## Performance

The indexing strategy is built around the dominant access pattern: **filter by `workspace_id`, then by status/foreign key.**

- **Every foreign key is indexed.** InnoDB requires it for FK enforcement and it accelerates joins (`subscriptions_workspace_id_index`, `memberships_user_id_index`, `roles_parent_id_index`, etc.).
- **Status/filter columns are indexed** because nearly every list view filters on them: the status FKs (`users_user_status_id_index`, `workspaces_workspace_status_id_index`, `memberships_membership_status_id_index`, `subscriptions_subscription_status_id_index`), `permissions_module_id_index`, `plans_is_active_index`, `activity_logs_action_index`.
- **Composite keys serve both uniqueness and lookup**: `(workspace_id, slug)`, `(workspace_id, key)`, `(workspace_id, user_id)`, `(workspace_id, provider)` are leftmost-prefixed on `workspace_id`, so the same index that enforces tenant-local uniqueness also supports `WHERE workspace_id = ?` scans.
- **Planned hot tables** get composite indexes matching their queries: `jobs (workspace_id, status)`, `applications (workspace_id, job_id, status, current_stage_id)`, `interviews (workspace_id, application_id, status)`, `notifications (user_id, read_at)`, `payments (workspace_id, gateway_reference)`.
- **FULLTEXT indexes** on the planned `jobs`/`applications` text columns power search behind the `SearchInterface` (see [28-Search-System.md](28-Search-System.md)), always combined with a `workspace_id` filter.
- **Pagination everywhere.** `QueryBuilder::paginate()` uses `LIMIT/OFFSET` so list endpoints never load whole tables; large logs (`activity_logs`, `notifications`) are always paginated and time-ordered.
- **N+1 avoidance.** Models expose batch helpers and joins (e.g. `User::workspaces()` joins `memberships`) rather than per-row queries.
- **OPcache + connection reuse.** A single PDO connection per request; OPcache holds the migration/model code. JSON columns are read into PHP arrays via `$casts` once per row.

## Testing

- **Migration tests**: run the full migration set against a scratch MySQL schema; assert all 16 built tables exist with the expected columns, that re-running yields no pending migrations, and that `down()` cleanly drops each table.
- **Convention tests**: assert every tenant model (`$tenantScoped = true`) maps to a table that actually has a `workspace_id` column with an FK + index; assert no MyISAM tables and that charset is `utf8mb4` everywhere.
- **FK behavior tests**: deleting a `workspace` cascades to its `memberships`/`settings`/`subscriptions`/`tenant_ai_keys`; deleting an `invited_by` user nulls `memberships.invited_by`; deleting a user who owns a workspace is **rejected**; deleting a plan with subscriptions is **rejected**.
- **Uniqueness/race tests**: concurrent inserts that violate `(workspace_id, user_id)` / `(workspace_id, slug)` raise an integrity error and are handled gracefully.
- **Seeder idempotency**: running `DatabaseSeeder::run()` twice does not duplicate the `Standard` plan, permissions, or the super-admin role.
- **Encryption round-trip**: `AiCredential::encryptSecrets()` → store → `secrets()` returns the original payload; the stored column is not human-readable.
- **Tenant isolation (security)**: a tenant-scoped `Model::query()` with no active tenant throws; `withoutTenantScope()` returns cross-tenant rows only for system code.

## Future Expansion

- **Planned tables 17–36** extend the schema along the documented domain (recruitment, AI interviews, billing, notifications, files, API, queue) using the exact same conventions; each ships as the next `NNNN_*` migration after its ERD entry is approved.
- **Read replicas.** Because the app is stateless and reads dominate, MySQL read replicas can serve heavy list/search queries; the connection layer can route reads to a replica with no schema change (see [36-Scalability.md](36-Scalability.md)).
- **Tenant sharding path.** Row-level isolation can evolve to "tenant → shard" mapping: the `workspace_id` already partitions every tenant table, so moving large tenants to dedicated databases is a routing change, not a re-modeling.
- **Search/queue offload.** The `SearchInterface` lets FULLTEXT be swapped for Meilisearch/Elasticsearch; the DB-backed `queued_jobs` can be swapped for Redis/SQS behind the same job API.
- **Soft deletes / archival.** If needed, a `deleted_at TIMESTAMP NULL` can be added per table and respected by the Model without breaking existing FKs.
- **Partitioning.** High-volume append-only tables (`activity_log`, `notifications`, `payments`) can be range-partitioned by `created_at` for cheaper retention/pruning.

## Open Questions

None at this time. The conventions above are fully determined by the canonical context; any future ambiguity (e.g. exact JSON shapes for planned `criteria`/`line_items`) will be resolved in the owning module's document and reflected back into [06-ERD.md](06-ERD.md).
