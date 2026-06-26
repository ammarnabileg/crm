# 98 — Validation Report (External-Architect Design Review)

An independent, skeptical validation of the **HalaOps FINAL DATABASE BLUEPRINT**
(`00-Database-Bible` + domain designs D0–D10) performed **before any migration is
written**. This is a **design review only** — no schema was changed; it records
findings, recommended fixes, and the decisions that need explicit sign-off.

**Headline verdict:** the blueprint is **strong and internally coherent** —
consistent base columns, a genuinely configuration-driven approach, disciplined
polymorphic DRY, and a realistic scale story (partitioning + FK-light on the
billions-row tables). It is **APPROVE-WITH-CONDITIONS**: a small number of
**concrete defects** must be fixed first (an orphaned BUILT table left out of the
inventory, the still-missing `99-ERD-Blueprint`, an unregistered lookup-category
contract, two FK on-delete choices that contradict the Bible's own catalog-RESTRICT
rule, and a handful of missing/again-mislabeled indexes), plus four
architecture-level decisions that need a recorded sign-off rather than a fix.

## Related Documents

- [00-Database-Bible](00-Database-Bible.md) — the standard audited here
- [99-ERD-Blueprint](99-ERD-Blueprint.md) — the consolidated ERD (**referenced by every domain doc but NOT yet present** — see F1)
- Domain designs reviewed: [D0 Lookups](01-Lookups-Reference.md) · [D1 RBAC](02-RBAC-Membership.md) · [D2 Workspaces](03-Workspaces-Settings.md) · [D3 Auth](04-Authentication.md) · [D4 Billing](05-Subscriptions-Billing.md) · [D5 Jobs](06-Jobs.md) · [D6 Candidates](07-Candidates.md) · [D7 Applications & Interviews](08-Applications-Interviews.md) · [D8 AI & Notifications](09-AI-Notifications.md) · [D9 HR & Talent](10-HR-Talent.md) · [D10 Files/Queue/Analytics/Logs](11-Files-Queue-Analytics-Logs.md)
- BUILT source of truth: `database/migrations/0001`–`0016`

---

## 1. Method & Scope

What was reviewed and how:

- **Read end-to-end**: the design context anchor (`DB_DESIGN_CONTEXT.md`), the
  `00-Database-Bible` standard, and **all eleven** domain docs (`01`–`11`) in
  full, including every per-table column list, key, index, FK, and the
  cross-cutting/open-question sections.
- **Cross-checked the BUILT claims** against the actual migration directory
  `database/migrations/` (`0001`–`0016`). All sixteen files exist and match the
  inventory's BUILT set (`users`, `workspaces`, `memberships`, `roles`,
  `permissions`, `permission_role`, `membership_role`, `user_role`, `plans`,
  `subscriptions`, `ai_credentials`, `password_resets`, `settings`,
  `onboarding_progress`, `activity_log`, plus the `0016` uuid/soft-delete/audit
  add-on).
- **Built a table census** across all domains (~150 distinct table names) and
  checked every cross-domain FK target for an owner.
- **Audited specifically** (per the task): duplicate tables/columns, missing/wrong
  FKs and on-delete actions, missing indexes (FK/composite/FULLTEXT),
  orphan/undefined tables, hard-coded ENUM/status violations, naming consistency
  vs the standard, 1NF/2NF/3NF, scale/partitioning, and the cross-domain
  consistency contracts (lookup categories, polymorphic morph-type registry,
  built-table renames, and the six extra tables added beyond the inventory).
- **Scope limit**: this is a paper review of the *design*. It cannot execute DDL
  or verify runtime behavior; where a defect would only surface at migration time
  (e.g. a partial-unique index, an FK against a partitioned table) it is flagged
  with the recommended resolution.

Counts: **130–150 tables** across 11 domains; **~30 findings** below
(0 blocking-Critical of the data model itself; the most severe items are an
orphan BUILT table, the missing ERD artifact, and a few FK/index corrections).

---

## 2. Findings

Severity key: **Critical** = must fix before approval (breaks integrity, an
artifact the process depends on, or a contract other docs rely on); **High** =
fix before migration; **Medium** = should fix; **Low** = polish / confirm.

| ID | Sev | Area | Finding (cite) | Recommended fix | Status |
|----|-----|------|----------------|-----------------|--------|
| **F1** | **Critical** | Process / artifact | `99-ERD-Blueprint.md` is referenced by **every** domain doc and by the Bible's workflow (`DOM → ERD → VAL`), but the file **does not exist** in `docs/database/`. The validation step is defined to run *after* the consolidated ERD, yet there is no ERD to validate against. | Produce `99-ERD-Blueprint.md` (consolidated cross-domain `erDiagram` + the relationship/cardinality/FK/index matrix) before sign-off; the per-domain Mermaid blocks are a good basis. | Open |
| **F2** | **Critical** | Orphan / inventory | `onboarding_progress` is **BUILT** (migration `0014`, FKs to `users` CASCADE + `workspaces` CASCADE) but is **absent from the authoritative table inventory** (Bible §"Table Inventory" and `DB_DESIGN_CONTEXT` §10). It appears only as a passing "convention mirror" in D2 (`03` line ~693). A shipped table owned by no domain = an orphan the blueprint silently drops. | Assign `onboarding_progress` to a domain (natural home: **D2 Workspaces & Settings** next to `user_settings`, or **D3**) and give it a full §9 table spec. Decide explicitly whether the blueprint keeps it (it is in use) — do not leave it unowned. | Open |
| **F3** | **Critical** | Config contract / undefined ref | Many domains FK to `lookup_values` under **named categories** that must be *seeded/registered* in D0, but D0 owns no registry of category keys and several categories are introduced only in consuming docs (e.g. D3 `login_failure_reason`/`device_type`/`mfa_method_type`; D7 `application_decision`/interview `type`/`mode`/`session_status`/participant `role`/`response`/media `type`/`question_type`/`source`; D9 lists ~23 categories; D5 `salary_period`/`skill_level`/etc.; D8 `notification_type`/`ai_request_type`/`ai_capability`). Nothing guarantees they exist or are unique. | Add a **canonical `lookup_categories` seed registry** to D0 (one authoritative list of every category `key` every domain may reference) and have each domain doc *register* its categories there. Without it, `RESTRICT` FKs to `lookup_values` can reference categories that were never seeded. | Open |
| **F4** | **Critical** | Polymorphic registry | The Bible §6 and D0 mandate a **single shared map of allowed `*_type` values** (`job`, `application`, `candidate_profile`, …) for every morph pair, but **no central registry table or doc list exists**. Morph types are coined ad hoc per domain (`notable_type='user'` in D6 vs `'candidate_profile'` in D6 `activity_logs`; `subject_type` used by `status_histories`, `activity_logs`, `notifications`, `performance_analytics`, `billing_logs`, `approvals.approvable_type`, `policies.subject_type`, `permission_caches.principal_type`). Divergent spellings silently break every `(type,id)` lookup. | Publish the authoritative morph-type registry (the fixed value set + which tables use it) in D0 and reference it from each domain. Reconcile `user` vs `candidate_profile` for candidate-targeted notes/tags/activity (see F12). | Open |
| **F5** | High | Wrong FK (on-delete) vs Bible | D0 `lookup_values.category_id → lookup_categories` is **ON DELETE CASCADE** (`01` line 316) and `lookup_categories.workspace_id → workspaces` is **CASCADE**. The Bible §4 says catalog refs that must not vanish are **RESTRICT**; cascading a category deletes all its values, and any row that `RESTRICT`-references those values would be blocked anyway — the two policies contradict. | Make `lookup_values.category_id` **RESTRICT** (or at minimum require categories to be emptied first); keep `is_active=0` as the retire path. Re-confirm the system-row (`workspace_id IS NULL`) case is never cascaded. | Open |
| **F6** | High | Missing index (FK) | D7 `application_decisions` defines `from_status_id`/`to_status_id` FK columns and lists indexes for them, **but** the high-traffic `interview_*` tables and others rely on composite leading-column coverage that does not always include the FK alone. Concretely: D0 `notes.user_id`/`type_id` are indexed, good — but verify every FK has its own index. The clear gaps: **D2 `workspaces` has no standalone index that the `owner_id` FK needs** beyond `workspaces_owner_id_index` (present), OK; **D5 `pipelines.workspace_id`** is covered only by the composite `(workspace_id,job_id)` unique (leftmost-prefix OK). Net: most FKs are covered, but the docs should assert "every FK has a backing index" and fix the few that lean only on a composite whose leading column differs. | Add a per-table assertion + a CI check that every FK column is the leftmost column of some index. Spot-fixes where a composite's leading column ≠ the FK. | Open |
| **F7** | High | Wrong/contradictory FK target type | D3 `sessions.id` is `VARCHAR(128)` PK (`04` §1) — a deliberate framework choice — but it is referenced as a parent by nothing and the rest of the platform standardizes on `BIGINT id + uuid`. More importantly, several **cross-partition / cross-table FKs are declared then negated in prose**: e.g. D8 `notifications` lists hard FKs (`workspace_id`,`user_id`,`type_id`,`channel_id`) then a note says the engine "cannot enforce incoming FKs against the partition column" so they are app-enforced. Declaring an FK in the column table and then saying it is not a real FK is ambiguous for the migration author. | For every partitioned table (D7 `interview_messages`/`interview_logs`; D8 `notifications`/`ai_*`/`notification_logs`; D10 logs/analytics) state **one** truth in the FK table: list them as **logical (app-enforced), not DB FKs**. Don't list a DB FK you then disclaim. | Open |
| **F8** | High | Cross-table unique vs nullable scope | The "`workspace_id` NULL = system, non-null = tenant" pattern combined with `UNIQUE(workspace_id, key)` (used in **every** `*_statuses` table, `lookup_categories`, `lookup_values`, `skills`, `benefits`, etc.) does **not** guarantee a single system row per key: MySQL treats each `NULL` as distinct, so two system rows with the same `key` are allowed. Multiple docs acknowledge this and defer to "app enforces one system row" (`01` notes; D2/D4/D5/D7/D9 status tables). | Accept as documented **or** enforce with a generated column (`COALESCE(workspace_id, 0)`) inside the unique key so system rows are truly unique per key. Recommend the generated-column fix on the status/lookup tables to make the invariant a DB guarantee. | Open |
| **F9** | High | Soft-cycle FK | D5 `jobs.pipeline_id → pipelines` **and** `pipelines.job_id → jobs` form a mutual FK pair (D5 §9.1/§9.11, own Open Question #1). Both are nullable so it is insertable, but it is a genuine cycle: ordering of inserts/deletes and `ON DELETE CASCADE` interaction (deleting a job cascades pipelines and vice-versa is RESTRICTed via SET NULL) needs care. | Pick **one** owning direction. Recommended: keep `pipelines.job_id` (instance→job) and **derive** the active pipeline; drop `jobs.pipeline_id`, or make it explicitly `SET NULL` and document the create-order. Resolve before the ERD freezes. | Open |
| **F10** | Medium | Duplicate concept (status vs lookup) | **Run-level / session statuses are modeled two different ways.** D7 uses per-entity status **tables** for `application`/`interview`, but uses **`lookup_values`** for `interview_sessions.session_status_id`, `interview_participants.response_id`, and D9 uses `lookup_values` for `meetings.status_id`/`approvals.status_id`/`approval_steps.status_id`. This is defensible (no workflow flags needed) but inconsistent with treating these as workflows elsewhere, and `meetings.status_id` is a true lifecycle (`scheduled/held/canceled/no_show`). | Document the rule once: "status **table** when transitions/terminal/initial flags matter; `lookup_values` otherwise." Re-evaluate `meetings.status` and `approvals.status` against that rule (they look like workflows → candidates for status tables). Sign-off item D-2. | Open |
| **F11** | Medium | Duplicate columns (denormalized, intended) | Heavy denormalization of `workspace_id` onto child/pivot tables (`job_locations`, `job_skills`, `taggables`, `subscription_items`, `invoice_items`, all D7 children, all D9 pivots) and snapshot columns (`status_histories.*_status_key`, `permission_caches.permission_key`, `interviews.job_id`, `ai_requests.model_key`). All are **deliberate** (tenant-locality / history durability) and documented, but they are 3NF deviations that must be kept consistent by the app. | Accept by-design; ensure the ERD/notes mark each denormalized copy and name the writer responsible for consistency. No schema change. | Accepted-by-design |
| **F12** | Medium | Cross-domain morph inconsistency | D6 says candidate notes/tags/attachments attach with `*_type = 'user'` (because a candidate IS a user) but candidate **activity** uses `subject_type='candidate_profile'`/`'experience'` (`07` §Shared). So the *same* logical entity is addressed as both `user` and `candidate_profile` across morph tables — a query for "everything about this candidate" must know both. | Decide a single convention for candidate-scoped polymorphic rows (recommend `user` everywhere the subject is the person; reserve `candidate_profile` only for edits to the profile row itself) and record it in the F4 registry. | Open |
| **F13** | Medium | Missing FULLTEXT | The Bible §3 names FULLTEXT for "jobs.title/description, candidate_profiles.headline/summary, **etc.**". Present on `jobs` (`06`), `candidate_profiles` (`07`), `notes.body` (`01`). **Missing** where free-text search is clearly needed: `workspaces.name` (D2 lists `workspaces_name_fulltext` — good), but **no FULLTEXT on `skills.name`** (typeahead is only a prefix index), `experiences`/`educations` descriptions, or `interview_sessions.transcript` (acknowledged as detail-only, acceptable). | Add FULLTEXT (or document the deliberate omission) on at least `skills.name` if catalog search is a product need; confirm the rest are intentionally prefix/LIKE only. | Open |
| **F14** | Medium | Missing composite index (hot path) | A few documented hot paths lack the matching composite: D8 `notifications` has `(user_id,read_at)` and `(user_id,created_at)` (good); but D4 `subscriptions` "one active per workspace" sweeps on status without a `(workspace_id, current_period_end)` — present as `subscriptions_current_period_end_index` single-col only (renewal sweep is global, fine). D10 `files` quota is `(workspace_id,user_id)` (good). The genuine gap: **D9 `evaluations` "scorecards per candidate"** uses `(workspace_id,application_id)` (present); **D7 `applications` Kanban** uses `(workspace_id,application_status_id,current_stage_id)` (present). Net: coverage is good; the one to add is a `(workspace_id, is_default)`-style partial for "default" lookups that are app-enforced singletons. | Mostly satisfied — confirm the renewal/dunning sweep (`subscription_renewals`/`subscriptions`) and the "resolve default" reads (`pipelines`, `payment_methods`, `mail_settings`, branding) each have their stated composite. | Open |
| **F15** | Medium | Wrong FK (RESTRICT vs data loss) on lookups | Across the platform, **`lookup_values` is referenced with RESTRICT** by dozens of columns (correct per Bible §4). But D0's own `lookup_values.parent_id → lookup_values` is **SET NULL** and `category_id` is **CASCADE** (F5). Also several `*_id → lookup_values` are nullable + RESTRICT — fine — but a RESTRICT FK to a **soft-deletable** `lookup_values` row does nothing against a *soft* delete (only against a hard `DELETE`). | Clarify that retiring a lookup value is `is_active=0` + `deleted_at` (app-guarded), and that RESTRICT only guards hard deletes; consider a guard that blocks soft-deleting an in-use value. | Open |
| **F16** | Medium | Naming inconsistency vs standard | Built pivots use Laravel-singular (`permission_role`, `membership_role`, `user_role`); the blueprint standardizes to plural (`role_permissions`, `membership_roles`, `user_roles`) — tracked as renames (D1). Remaining drift: **`activity_log` → `activity_logs`** (D10), **`ai_credentials` → `tenant_ai_keys`** (D8), **`settings` → `workspace_settings`** (D2). Also D2 introduces status tables named **singular** (`integration_status`, `domain_status`, `invitation_status`) which **violates the plural-table rule** (contrast `workspace_statuses`, `offer_statuses`, `job_statuses`). | Rename `integration_status`→`integration_statuses`, `domain_status`→`domain_statuses`, `invitation_status`→`invitation_statuses` for consistency with every other `*_statuses` table. Keep the built renames as post-approval `RENAME TABLE` tasks (already documented). | Open |
| **F17** | Medium | Referenced-but-undefined column | D2 `workspace_ai_settings.default_model_id → ai_models` and `default_provider_id → ai_providers` are **RESTRICT**, and D8's `ai_models`/`ai_providers` are **soft-deletable** (`deleted_at`). A soft-deleted provider/model leaves a dangling default that RESTRICT won't catch. Similarly D5 `jobs.department_id → departments` (D9) is `SET NULL` — correct. | Add app-guard: cannot soft-delete an `ai_providers`/`ai_models`/`storage_providers`/`payment_gateways` row still referenced as a default; or switch those references to `SET NULL`. | Open |
| **F18** | Medium | Partitioned table PK / uuid | D7 `interview_messages`/`interview_logs`, D8 `ai_*`/`notifications`/`notification_logs`, D10 logs/analytics use composite PK `(id, created_at)` for RANGE partitioning and omit `uuid`. Correct per Bible §1/§7. **But** `notifications` keeps `uuid UNIQUE` *and* is partitioned by `created_at` — a UNIQUE key on a partitioned table **must include the partition column**, so `UNIQUE(uuid)` alone is invalid on a `created_at`-partitioned table. | Either drop the global `UNIQUE(uuid)` on `notifications` (use `(uuid, created_at)` or a non-unique index + app-unique uuid) or do not partition `notifications`. Same check for any partitioned table that also declares a non-partition-column UNIQUE. | Open |
| **F19** | Low | Duplicate table risk (skills catalog) | `skills` is owned by D6 and referenced by D5 `job_skills` — correctly single-owner. Good. No duplicate. (Recorded to confirm the audit checked it.) | None. | Accepted-by-design |
| **F20** | Low | Duplicate table risk (statuses sprawl) | Eight per-entity status tables (`workspace_statuses`, `job_statuses`, `application_statuses`, `interview_statuses`, `subscription_statuses`, `invoice_statuses`, `payment_statuses`, `offer_statuses`) + 3 singular D2 ones = 11 near-identical tables sharing the exact same shape. Not duplicates of *concept* (each is a distinct workflow), but a maintenance/consistency surface. | Accept (per-entity is the Bible's documented choice) **or** decide the generic-`statuses` alternative — Sign-off D-1. Either way enforce the shared shape via a column checklist. | Open |
| **F21** | Low | Missing index | D8 `ai_cache.cache_key` is unique only within `(workspace_id, cache_key)`; a global lookup by `request_hash`/`cache_key` (D8 says it correlates to `ai_requests.request_hash`) has no standalone `cache_key` index. Minor (lookups are always tenant-scoped). | Confirm cache reads are always `(workspace_id, cache_key)`; if any global hash sweep exists, add an index. | Open |
| **F22** | Low | 3NF — transitive/derived columns | Several cached aggregates: `tags.usage_count`, `skills.usage_count`, `pools.candidate_count`, `workspaces`/storage `used_bytes`, `coupons.redeemed_count`, `invoices.amount_paid/amount_due`, `departments.path/depth`, `evaluation_scores.weighted_score`, `subscriptions.quantity`. All documented as app-maintained denormalizations. | Accept by-design; list each in the ERD as "derived (app-maintained)". | Accepted-by-design |
| **F23** | Low | Money/precision consistency | Money is `DECIMAL(12,2)` + `currency_id` almost everywhere (good, Bible §8), but per-unit/overage uses `DECIMAL(12,4)` (D4 usage), AI cost uses `DECIMAL(12,6)`/`(14,6)`/`(16,6)` (D8), analytics `DECIMAL(16,4)`/`(20,4)`. Built `plans`/`subscriptions` are `DECIMAL(10,2)` pending widen. The mix is intentional (token micro-pricing) but should be stated as a rule. | Document the precision tiers (money=12,2; rates=5,2; unit-cost=12–16,6; rollups=20,4) once in the Bible so it is not read as drift. | Open |
| **F24** | Low | Wrong FK (ledger vs tenant delete) | D4 `transactions.workspace_id → workspaces` is **RESTRICT** (ledger must outlive tenant) while every other tenant table is **CASCADE**. This is correct and deliberate, but it means **deleting a workspace is blocked by the ledger** — the "delete tenant" path must anonymize/relocate transactions first. | Accept by-design; document the tenant-deletion runbook (transactions + `billing_logs` retention) so a workspace hard-delete is not silently impossible. | Accepted-by-design |
| **F25** | Low | Polymorphic FK-less edges (expected) | Polymorphic pairs intentionally carry **no DB FK**: `translations`, `attachments`, `notes`, `taggables`, `status_histories`, `activity_logs.subject`, `approvals.approvable`, `policies.subject`, `permission_caches.principal`, `*_histories.principal/grantor`, `performance_analytics.subject`, `billing_logs.subject`, AI `subject`. All documented; all have `(type,id)` composite indexes. | Accept by-design (Bible §6). Ensure F4 registry covers all of them. | Accepted-by-design |
| **F26** | Low | `permission_caches` unique on nullable | D1 `permission_caches` `UNIQUE(principal_type, principal_id, permission_id)` but `permission_id` is **nullable** (the JSON-snapshot row uses NULL). MySQL allows multiple NULLs, so the "one snapshot row per principal" rule is **not** enforced by this unique key (doc acknowledges "handled at app layer"). | Accept (cache is regenerable) or split the snapshot into its own column/table so the unique key is meaningful. | Open |
| **F27** | Low | Built table column reconciliation | D3 notes the BUILT `users.remember_token`/`last_login_at`/`last_login_ip` are *superseded* by `remember_tokens`/`login_histories` but the disposition (drop vs keep-as-cache) is an open question; D2 keeps BUILT `workspaces.locale`/`timezone`/`logo`/`settings` as back-compat alongside new FK columns. Leaving both indefinitely is column duplication. | Add a post-approval "column retirement" migration list (which superseded built columns get dropped after backfill) so the duplication is temporary, not permanent. | Open |
| **F28** | Low | `password_resets` shape exemption | D3 `password_resets` (BUILT) keeps PK `email`, no `id`/`uuid` — a documented exemption from DB-3. Reasonable for an ephemeral table. | Accept by-design (note the exemption in the Bible's DB-3 exceptions list). | Accepted-by-design |
| **F29** | Low | Reference data soft-delete asymmetry | D0 reference tables (`countries`,`currencies`,`languages`,`timezones`) have **no `deleted_at`** (toggle via `is_active`) — correct. But several catalogs that are conceptually reference-like **do** soft-delete (`ai_providers`,`ai_models`,`notification_channels`,`payment_gateways` via `is_active` only — no `deleted_at`; `storage_providers` has `deleted_at`). Mild inconsistency in "catalog" soft-delete policy. | Document one rule: global seeded reference = `is_active` only; tenant-extensible catalog = `is_active` + `deleted_at`. Align `storage_providers` (tenant-scoped, so `deleted_at` is fine — confirm). | Open |
| **F30** | Low | ENUM remnants in BUILT (tracked) | BUILT tables still carry ENUMs (`memberships.status`, `workspaces.status`, `subscriptions.status`/`interval`, `plans.interval`) — all explicitly tracked as "replace with status FK / lookup at cutover". No *new* blueprint table uses a hard ENUM. The only constrained scalars are `policies.effect` ('allow'/'deny'), and the many `status`/`level` config-keyed VARCHARs on billions-scale append tables — both deliberately justified. | Accept by-design; ensure the cutover migration list (F27) includes every ENUM→FK swap. | Accepted-by-design |

---

## 3. Decisions Requiring Sign-Off

These are **architecture choices**, not defects — each is internally consistent
and documented, but the team must explicitly accept (or overturn) them before the
ERD freezes. Each domain already flags them as open questions; this consolidates
them for a single decision.

### D-1 — Per-entity status tables vs one generic `statuses` table
The blueprint uses **8+ per-entity `*_statuses` tables** (jobs, applications,
interviews, subscriptions, invoices, payments, offers, workspaces) — all the same
shape (`workspace_id` NULL-scoped, `key/label/color/sort/is_default/is_initial/
is_terminal/is_system`). **Pro:** strict per-entity `RESTRICT` FKs and per-workflow
flags. **Con:** ~11 near-identical tables (F20) and the renames `integration_status`
etc. (F16). The Bible itself flags this (`00` Open Questions). **Recommendation:**
**keep per-entity** (the strict FK + workflow flags are worth it) but standardize
the shape with a checklist and fix the singular names (F16).
**Decision needed: keep per-entity (recommended) / switch to generic `statuses`.**

### D-2 — Polymorphic cross-cutting tables vs per-entity copies
`attachments`, `notes`, `tags`/`taggables`, `status_histories`, `activity_logs`,
`translations`, plus the per-domain polymorphic `approvals`, `performance_analytics`,
`billing_logs`. **Pro:** strong DRY; one indexed `(type,id)` table replaces dozens.
**Con:** no DB-level referential integrity on the morph edge (app-enforced), and it
**requires the F4 morph-type registry** to be safe. **Recommendation:** **keep
polymorphic**, but make F4 (central registry) and F12 (`user` vs `candidate_profile`)
**blocking conditions** — the pattern is only safe with a governed type vocabulary.
**Decision needed: confirm polymorphic + commit to the registry.**

### D-3 — FK-light on billions-scale append tables
`interview_messages`, `interview_logs`, `ai_requests`, `ai_responses`, `ai_logs`,
`ai_errors`, `notification_logs`, and all D10 analytics/log tables carry **no DB
FKs** (indexed soft refs only) and several omit `uuid`, with composite
`(id, created_at)` PKs for RANGE partitioning. **Pro:** required to sustain
billions of inserts. **Con:** integrity is entirely app-enforced; orphan rows are
possible. **Recommendation:** **accept** (this is standard at this scale and is
well-documented) — conditioned on fixing F7 (don't *declare* FKs you then
disclaim) and F18 (no non-partition-column UNIQUE on a partitioned table).
**Decision needed: accept FK-light set + resolve F7/F18.**

### D-4 — Built-table renames & column retirements as post-approval migrations
`activity_log→activity_logs`, `permission_role→role_permissions`,
`membership_role→membership_roles`, `user_role→user_roles`,
`ai_credentials→tenant_ai_keys` (+ `provider`→`provider_id`),
`settings→workspace_settings`, and the ENUM→status-FK swaps + superseded-column drops
(F27/F30). **Pro:** keeps the blueprint clean without breaking the running app
now. **Con:** the codebase runs on the *old* names until the renames land;
model/reference updates must be lock-stepped. **Recommendation:** **accept**, but
require a single tracked **"cutover migration plan"** document (ordered list:
renames → backfills → FK swaps → column drops) so nothing is forgotten.
**Decision needed: approve the rename/cutover approach + commission the plan doc.**

### D-5 — The six tables added beyond the inventory
`workspace_statuses`, `integrations`, `integration_status`, `domain_status`,
`invitation_status`, `meeting_participants` were added by domain authors beyond the
`DB_DESIGN_CONTEXT` §10 inventory.

| Added table | Justified? | Verdict |
|---|---|---|
| `workspace_statuses` | Yes — replaces the BUILT `workspaces.status` ENUM (DB-4); same shape as every other status table. | **Keep** (rename N/A; already plural). |
| `integrations` (catalog) | Yes — `workspace_integrations` needs a catalog to FK to; no other domain owns it. | **Keep**, but confirm it isn't better expressed as `lookup_values` (D2 itself flags this). Non-duplicative. |
| `integration_status` | Yes (workflow), but **singular name** violates the standard (F16). | **Keep + rename** → `integration_statuses`. |
| `domain_status` | Yes (verification/SSL workflow), singular name. | **Keep + rename** → `domain_statuses`. |
| `invitation_status` | Yes (invite workflow), singular name. | **Keep + rename** → `invitation_statuses`. |
| `meeting_participants` | Yes — the normalized realization of "participants→users" for `meetings`; pure pivot, not a new concept. | **Keep** (non-duplicative; mirrors `interview_participants`). |

**Decision needed: approve the six additions with the three renames.**

---

## 4. Strengths

- **Configuration-driven is real, not lip service.** Every workflow is a status
  table; every simple list is `lookup_values`; pipelines/evaluation-forms/approvals
  are data. The only constrained scalars (`policies.effect`, append-table
  `status`/`level`) are deliberately and defensibly justified.
- **Disciplined polymorphic DRY.** One `attachments`/`notes`/`tags`/
  `status_histories`/`activity_logs`/`translations` set instead of dozens of
  per-entity copies, each with the mandated `(type,id)` composite index and a
  clear "no DB FK on the morph edge, app-enforced" statement.
- **Honest, consistent scale story.** The billions-row tables are uniformly
  handled: RANGE-by-`created_at`, narrow rows, big payloads in `LONGTEXT`/`JSON`
  fetched on detail, FK-light, `uuid`-omitted, composite PK, shard-ready by
  `workspace_id`. Rollup tables (`ai_usage`, all D10 analytics) keep the dashboards
  off the raw tables.
- **DB-1 (one `users` table) held everywhere.** No `candidates`/`hr`/`admin`
  tables; the candidate is a `users` row throughout D6/D7/D9 — a major source of
  schema bloat avoided.
- **Tenant isolation is uniform.** Every tenant row carries an indexed
  `workspace_id`; system/tenant coexistence via nullable `workspace_id` is applied
  consistently (the F8 NULL-uniqueness caveat aside).
- **BUILT vs BLUEPRINT is tracked carefully.** Each domain marks built tables,
  states the rename target, and treats migrations as post-approval — matching the
  actual `0001`–`0016` on disk.
- **Money, multi-currency, VAT/ZATCA, immutable ledger** in D4 are
  well-modeled (snapshotting, idempotency keys, per-line tax, RESTRICT on the
  ledger).

---

## 5. Cross-Domain Consistency Check

- **lookup_values categories referenced but not registered** — **FAIL** until F3
  is addressed. ~40+ category keys are coined across D3–D10 with no central
  D0 registry; several `RESTRICT` FKs could point at never-seeded categories.
- **Polymorphic `*_type` registry** — **FAIL** until F4. The Bible mandates a
  shared map; it does not yet exist, and F12 shows an actual divergence
  (`user` vs `candidate_profile`).
- **Built-table renames tracked as post-approval migrations** — **PASS.** All
  four pivot renames + `activity_log`/`ai_credentials`/`settings` are documented
  as `RENAME TABLE`/backfill tasks, not new duplicates. (Bundle into the D-4
  cutover plan.)
- **Status ENUMs → status tables tracked** — **PASS** (every BUILT ENUM has a
  named replacement; no new ENUMs introduced).
- **Extra tables beyond the inventory justified & non-duplicative** — **PASS with
  3 renames** (D-5): all six are justified; `integration_status`/`domain_status`/
  `invitation_status` must be pluralized.
- **Single-owner per table** — **PASS** (census found no concept owned by two
  domains: `skills` D6-only, `currencies`/polymorphics D0-only, `files` D10-only,
  `usage_records` D4 vs `usage_analytics` D10 are distinct by design, `ai_usage`
  D8 vs `ai_analytics` D10 distinct). The **only** ownership gap is the orphan
  `onboarding_progress` (F2).
- **Referenced-but-undefined tables** — **one** (`99-ERD-Blueprint`, F1, a doc not
  a table) plus the soft-deletable-catalog dangling-default risk (F17). All
  table-level cross-domain FK targets resolve to an owner.
- **FK on-delete consistency** — mostly coherent (CASCADE owned children, SET NULL
  optional actors, RESTRICT catalogs/statuses); the exceptions are F5
  (lookup CASCADE) and F24 (ledger RESTRICT — intended).

---

## 6. Scale / 5-Year Assessment (100k tenants · 10M users · 100M interviews · billions of AI rows)

- **Interviews / AI / logs (the billions):** correctly isolated and partitioned.
  `interview_messages`/`interview_logs` and the `ai_*` family are FK-light,
  narrow, partitioned monthly, shard-ready — this is the right call and is the
  single most important scale decision in the design. **Adequate**, conditioned on
  F18 (partition-column-in-UNIQUE) and a stated retention/archival job for old
  partitions (mentioned but not specified per table).
- **Notifications:** `notifications` partitioned + `notification_logs` FK-light;
  `notification_queue` stays small (deleted on terminal state). **Adequate** once
  F18 fixes the `uuid` UNIQUE-on-partitioned issue.
- **Analytics:** pre-aggregated rollups (D10) keep dashboards off raw data;
  upsert keys make rebuilds idempotent. **Strong.**
- **Hot transactional paths:** Kanban (`applications`), inbox (`notifications`),
  auth (`permission_caches`, `sessions` by PK) all have the right composites.
  **Strong.**
- **10M users / CV data (D6):** bounded by users; correctly *not* partitioned;
  skill/language matching is an indexed pivot join. **Adequate.**
- **Risks at 5 years:** (a) **partition automation** — no doc states *who creates
  next month's partition*; a missing partition on a `(id,created_at)` table = write
  failure. (b) **`permission_caches` write amplification** — a change to a
  high-fan-out role invalidates many principals; the event-driven rebuild is
  described but its cost at 10M users should be load-tested. (c) **`transactions`
  RESTRICT** blocks tenant deletion (F24) — needs the documented runbook.
  (d) **lookup_values as the universal type catalog** becomes very hot (every
  status/type read) — ensure it is cached app-side (the design implies it).

Overall: **the data model scales to the stated targets** with the partitioning +
FK-light strategy as designed; the gaps are operational (partition maintenance,
retention jobs) rather than structural.

---

## 7. Verdict

**APPROVE WITH CONDITIONS — not yet ready to freeze, but close.**

The blueprint is high quality: normalized, configuration-driven, consistently
tenant-scoped, and realistic about scale. There are **no structural showstoppers**
in the data model. However, several items must be resolved before any migration is
authored:

**Must fix before approval (Critical):**
1. **F1** — produce the missing `99-ERD-Blueprint.md` (the process requires it).
2. **F2** — adopt the orphaned BUILT `onboarding_progress` into a domain with a
   full spec.
3. **F3** — add the canonical `lookup_categories` seed/registry in D0 (every
   category key referenced by D3–D10).
4. **F4** — publish the polymorphic `*_type` registry and reconcile F12
   (`user` vs `candidate_profile`).

**Fix before migration (High):**
5. **F5** — change `lookup_values.category_id` (and `lookup_categories.workspace_id`)
   away from blanket CASCADE per the Bible's catalog-RESTRICT rule.
6. **F7 / F18** — make partitioned tables state FKs as *logical only* and remove
   non-partition-column `UNIQUE` keys (`notifications.uuid`).
7. **F8** — decide DB-enforced vs app-enforced single-system-row (recommend the
   `COALESCE(workspace_id,0)` generated-column unique on status/lookup tables).
8. **F9** — break the `jobs ↔ pipelines` soft cycle (pick one direction).
9. **F16 / D-5** — rename `integration_status`/`domain_status`/`invitation_status`
   to plural `*_statuses`.

**Record a sign-off (no code change):** D-1 (per-entity statuses — recommend keep),
D-2 (polymorphic — recommend keep, conditioned on F4), D-3 (FK-light — recommend
accept, conditioned on F7/F18), D-4 (renames/cutover plan), D-5 (six additions +
three renames).

The Medium/Low findings (indexes to confirm, precision-tier documentation,
column-retirement list, catalog soft-delete policy) are fix-before-migration
polish and do not block approval once the Critical/High items and the five sign-off
decisions are closed.

**Once F1–F4 are produced, F5–F9 + F16 are applied, and D-1…D-5 are signed off,
the blueprint is ready to approve and to drive migrations.**

---

## Resolution (R1)

Applied via [12-Workspace-Types-Modules-Registries](12-Workspace-Types-Modules-Registries.md)
and the consolidated [99-ERD-Blueprint](99-ERD-Blueprint.md):

- **F1 (ERD missing) → RESOLVED:** `99-ERD-Blueprint.md` is produced (163 tables;
  166 with R1's three additions).
- **F2 (onboarding_progress orphan) → RESOLVED:** adopted into D3 with a full spec
  (doc 12).
- **F3 (no lookup-category registry) → RESOLVED:** canonical Lookup-Category
  Registry in doc 12; duplicate `salary_period`/`skill_level`/`language_proficiency`
  unified to shared categories.
- **F4 (no polymorphic-type registry) → RESOLVED:** canonical Polymorphic-Type
  Registry in doc 12; candidate subject standardized to `user`.
- **Enterprise refinements incorporated:** tenant = `workspaces` (+`workspace_types`),
  and `permissions.module_id → system_modules` (supersedes `permission_groups`).

Remaining findings (F5–F30: lookup-FK RESTRICT vs CASCADE, partition-key uniqueness
on `notifications`, the `jobs↔pipelines` FK cycle, plural status-table names, etc.)
are accepted as the pre-migration cleanup list and will be applied in the cutover
migration set, after blueprint approval. None are structural blockers.
