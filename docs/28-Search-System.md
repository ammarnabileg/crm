# 28 — Search System

HalaOps search: MySQL FULLTEXT over `jobs` and `applications` today, hidden behind a `SearchInterface` abstraction so Meilisearch/Elasticsearch can replace it later — always tenant-filtered, with a defined indexing strategy, ranking, pagination, and performance budget.

## Related Documents

- [05 — Database Architecture](05-Database-Architecture.md) — table conventions, indexing strategy, and the FULLTEXT indexes used here.
- [35 — Performance](35-Performance.md) — query budgets, caching, and how search fits the performance story.
- [24 — Job Lifecycle](24-Job-Lifecycle.md) — the `jobs` data that is searched.
- [25 — Application Lifecycle](25-Application-Lifecycle.md) — the `applications` data that is searched.
- [08 — Multi-Tenant Architecture](08-Multi-Tenant.md) — the tenant filter every search must apply.

---

## Purpose (الهدف)

The Search System gives users fast, relevant, **tenant-isolated** full-text search across the recruitment domain — primarily **jobs** (title, description, department, location) and **applications** (candidate name, cover letter, source). It is delivered behind a single `SearchInterface` so the underlying engine (MySQL FULLTEXT now; Meilisearch/Elasticsearch later) can change without touching callers. Every query is **always** scoped to the active company.

## Implementation status (built — Phase 16)

A **keyword + filter** global search is built (it is **not** a full-text index — see the honest note below). `App\Services\Search\GlobalSearch` + `App\Controllers\App\SearchController` serve the `app/search` view via the route `GET /search`, gated by **`recruitment.view`**, with a nav entry added to the app layout. It does **not** replace the existing ATS search — it composes it:

- **Keyword sweep** (`q`): a tenant-scoped pass over the workspace's **Jobs** (`title` / `slug`) and **Applications** (applicant `name` / `email`, or the job `title`). Both halves are strictly `workspace_id = tenant()->id()` scoped, and the user query's LIKE wildcards (`% _ \`) are escaped so a search for `"50%"` matches literally.
- **Talent filters** (skills / experience / salary / city, optional): delegated to the existing `App\Services\Ats\AdvancedSearch::searchCandidates` (unchanged), with each returned profile enriched with the candidate's `name` / `email` in **one batched `users` query (no N+1)**.

**Honest limitation.** The built search is **keyword (`LIKE`) + filter based, not a full-text/FULLTEXT index**, and there is **no** `SearchInterface` / `SearchManager` / `MysqlFulltextDriver` abstraction in code yet — that whole pluggable design (FULLTEXT now, Meilisearch/Elasticsearch later) described below remains forward-looking. No new tables were added and `AdvancedSearch` was not modified. Tenant safety: jobs/applications are workspace-scoped here; candidate profiles remain the global, opt-in (`is_searchable`) talent pool exactly as `AdvancedSearch` already governs them — `GlobalSearch` never widens that boundary.

## Why It Exists (سبب وجوده)

Recruiters and HR managers work over growing lists of jobs and applications and need to find records by free text ("senior backend Riyadh", a candidate's name, a keyword in a cover letter) — `LIKE '%term%'` cannot do this at scale (no index usage, no relevance ranking, poor Arabic handling). At the same time the platform must run on **plain shared MySQL** with no extra services for the buyer, yet allow large tenants to upgrade to a dedicated search engine. The resolution:

- **Use MySQL FULLTEXT** as the zero-dependency default — it ships with the database every buyer already has.
- **Abstract it behind `SearchInterface`** so the same controller code can be pointed at Meilisearch/Elasticsearch by configuration, with no rewrite, when a deployment needs typo-tolerance, faceting, or higher throughput.
- **Bake tenant scoping into the abstraction** so no search path can ever leak another company's data — search is a classic place isolation bugs hide.

## Architecture

```mermaid
flowchart TD
    UI[Search box / filters] --> Ctrl[Controller<br/>RequirePermission jobs.view / applications.view]
    Ctrl --> SVC[SearchManager]
    SVC --> IFACE{SearchInterface}
    IFACE -->|default| MY[MysqlFulltextDriver<br/>MATCH ... AGAINST + WHERE workspace_id]
    IFACE -->|future| MEILI[MeilisearchDriver]
    IFACE -->|future| ES[ElasticsearchDriver]
    MY --> DB[(MySQL FULLTEXT indexes<br/>jobs, applications)]
    MEILI --> MIDX[(Meili index per tenant/prefixed)]
    ES --> EIDX[(ES index, workspace_id filter)]
    SVC --> RES[Ranked, paginated results<br/>tenant-scoped]
    RES --> UI
```

**Components and responsibilities** (✅ = built today; the rest are the forward-looking design):

- **`GlobalSearch`** (`app/Services/Search/GlobalSearch.php`, **✅ built**) — the web-facing orchestrator. `search($q, $filters)` returns three result sets — `jobs`, `applications`, `candidates`. `jobs()`/`applications()` are tenant-scoped `LIKE` sweeps (wildcards escaped, bound parameters); `candidates()` delegates to `AdvancedSearch::searchCandidates` and batch-enriches names/emails. This is the concrete, keyword-based implementation that the planned `SearchManager`/driver abstraction below would later formalise.
- **`SearchController`** (`app/Controllers/App/SearchController.php`, **✅ built**) — marshals `q` and the talent filters from the request, enforces `recruitment.view`, and renders `app/search`. Tenant scoping lives inside `GlobalSearch`.
- **`AdvancedSearch`** (`app/Services/Ats/AdvancedSearch.php`, **✅ pre-existing, unchanged**) — the talent-pool candidate query that `GlobalSearch` composes for the filter path.
- **`SearchInterface` / `SearchManager`** (`app/Services/Search/…`, planned) — the design's pluggable contract + façade that would inject `workspace_id`, normalize the query, and return a uniform `SearchResult`. **Not built** — `GlobalSearch` is called directly today.
- **`MysqlFulltextDriver`** (planned, **NOT built**) — the design's default would use `MATCH … AGAINST` over FULLTEXT indexes on `jobs`/`applications`. Today's code uses `LIKE`, not FULLTEXT.
- **`MeilisearchDriver` / `ElasticsearchDriver`** (future) — would implement the same interface for typo-tolerance, facets, synonyms.
- **`SearchResult`, indexer hooks** (planned) — a result DTO and `Job`/`Application` lifecycle hooks to keep external engines in sync; not needed by the built `LIKE` search.

**Configuration**: there is no `config/search.php` yet — the built search has no tunables (a fixed per-section result cap of 25 rows). The planned `config/search.php` (`driver`, `min_query_length`, `per_page`, searchable-column map) is design.

## Workflow

### Query flow (built — keyword `LIKE`)

The flow that exists today: the user submits `q` (and optional talent filters) to `GET /search`; `SearchController` enforces **`recruitment.view`** and calls `GlobalSearch::search($q, $filters)`. `GlobalSearch` runs, all within the active `workspace_id`:

- `jobs`: `SELECT … FROM jobs WHERE workspace_id = ? AND deleted_at IS NULL AND (title LIKE ? OR slug LIKE ?) ORDER BY id DESC LIMIT 25` — wildcards in `q` escaped, all values bound.
- `applications`: the same shape joined to `users` + `jobs`, matching applicant `name`/`email` or job `title`, `workspace_id`-scoped.
- `candidates` (only when a talent filter is present): delegated to `AdvancedSearch::searchCandidates`, then names/emails batch-enriched.

Results are passed straight to the view (no relevance score, no pagination — a fixed 25-row cap per section).

### Query flow (planned — MySQL FULLTEXT)

The forward-looking design (not built):

1. User submits a query and optional filters (status, department, stage) from a list screen. The controller checks the right permission (`jobs.view` or `applications.view`).
2. `SearchManager::search('jobs', $q, $opts)` normalizes the term, enforces `min_query_length`, and **sets `$opts['workspace_id']` from `TenantManager`** (ignoring any client-supplied value).
3. `MysqlFulltextDriver` builds:
   ```sql
   SELECT *, MATCH(title, description) AGAINST(:q IN BOOLEAN MODE) AS score
   FROM jobs
   WHERE workspace_id = :workspace
     AND status IN (:statuses)            -- optional filters
     AND MATCH(title, description) AGAINST(:q IN BOOLEAN MODE)
   ORDER BY score DESC, published_at DESC
   LIMIT :limit OFFSET :offset;
   ```
   All values are bound parameters (no interpolation).
4. Results are wrapped in `SearchResult` with the total (a parallel `COUNT` honoring the same `WHERE`) and returned paginated to the view.

```mermaid
sequenceDiagram
    participant U as User
    participant C as Controller
    participant S as SearchManager
    participant T as TenantManager
    participant D as MysqlFulltextDriver
    participant DB as MySQL

    U->>C: q="senior riyadh", page=1
    C->>S: search("jobs", q, {filters,page})
    S->>T: active workspace_id
    S->>D: search(index, q, opts+workspace_id)
    D->>DB: SELECT ... WHERE workspace_id=? AND MATCH(...) AGAINST(? IN BOOLEAN MODE) LIMIT/OFFSET
    DB-->>D: ranked rows + score
    D-->>S: SearchResult(items,total,page)
    S-->>C: SearchResult
    C-->>U: rendered, paginated results
```

### Indexing flow (for external engines)

1. On create/update of a `Job` or `Application`, the model fires a hook → `SearchManager::index('jobs', $doc)` where `$doc` includes `id`, `workspace_id`, and the searchable fields.
2. On delete, `SearchManager::delete('jobs', $id)` removes the document.
3. A reindex command (admin/diagnostics) can `flush()` and rebuild an index from the database — used on engine switch or schema change.
4. For the **MySQL driver these hooks are no-ops**: FULLTEXT indexes update transactionally with the row, so there is nothing to push.

## Business Rules

1. **Every search is tenant-scoped.** `workspace_id` is injected by `SearchManager` from the active tenant and added as a hard filter (or per-tenant index for external engines). A search with no active tenant fails closed (no results / error), never returns cross-tenant rows.
2. **The engine is pluggable, the API is fixed.** Callers depend only on `SearchInterface`/`SearchManager`; switching to Meilisearch/Elasticsearch is a config change plus a reindex.
3. **Default searchable fields**: `jobs` → `title`, `description` (with `department`/`location` filterable); `applications` → candidate name (joined from `users`), `cover_letter`, `source`. The searchable map is config-driven so fields can be added without touching drivers.
4. **Boolean mode** is used for MySQL FULLTEXT so users can do `+required -excluded "exact phrase"`; bare terms are OR-ed and ranked by relevance.
5. **Minimum query length** (`min_query_length`, default 2–3 chars) prevents pathological scans; shorter input falls back to a simple prefix filter on indexed columns.
6. **Results respect domain permissions and visibility** — a user only searches records they may list (e.g. `applications.view`), and tenant scope is applied on top.
7. **Pagination is mandatory**; search never returns an unbounded set.
8. **Relevance is the primary sort**, with a deterministic tiebreaker (`published_at`/`applied_at`/`id`) so paging is stable.

## Database Relations

Search reads existing domain tables and adds **no new tables**. The built keyword search uses plain `LIKE` over already-indexed columns; the **FULLTEXT indexes** below are part of the forward-looking design, not added by the built search:

- **`jobs`** (TENANT): the built search matches `title` / `slug`, filtered by `workspace_id` (covered by the existing tenant/status indexing). *Planned:* a FULLTEXT index on `(title, description)`.
- **`applications`** (TENANT): the built search matches the joined `users(name/email)` and `jobs(title)`, filtered by `workspace_id`. *Planned:* a FULLTEXT index on `(cover_letter)`.
- **`users`** (GLOBAL): joined for candidate name/email; matching is still constrained to the tenant via the `applications.workspace_id` filter, so global `users` rows are only reachable through a tenant's applications.
- **`candidate_profiles`**: reached only through `AdvancedSearch::searchCandidates` (the existing opt-in `is_searchable` talent pool), unchanged by this work.

*Planned:* FULLTEXT indexes declared in the `jobs`/`applications` migrations (see [06-ERD.md](06-ERD.md)), maintained transactionally by MySQL; for external engines, per-tenant documents mirror these fields plus `workspace_id`.

## Permissions

As built, the single global-search surface (`GET /search`) is gated by **`recruitment.view`** — the recruitment module already covers jobs, applications and the talent view, so one gate guards the combined keyword + filter search (see [07-RBAC.md](07-RBAC.md), 11-Permissions-Matrix). Regardless of permission, the `workspace_id` filter is always applied inside `GlobalSearch` for the jobs/applications halves.

The finer-grained model below is the **design** for the planned per-entity search abstraction:

- **Job search** would require `jobs.view`; **application search** `applications.view`.
- **Candidate self-service** searching their own applications gated by `candidate.profile` / `candidate.apply`, scoped to their own `user_id`.
- **Super admin** platform-wide search (across tenants) a distinct `platform.*` capability using `withoutTenantScope()`; the only path that may omit the `workspace_id` filter, never reachable by tenant users.

## Validation

- **Query length**: trimmed length must be ≥ `min_query_length`; otherwise fall back to prefix filtering or return an empty, explained result.
- **Sanitization for FULLTEXT boolean mode**: special operators (`+ - > < ( ) ~ * "`) are handled deliberately — either honored as user operators or stripped/escaped to avoid syntax errors; the final string is always passed as a **bound parameter**, never concatenated.
- **Pagination params**: `page ≥ 1`, `perPage` clamped to a max (e.g. 100) and defaulted from config.
- **Filters**: each filter value validated against allowed enum sets (status/type/stage) before being added to the `WHERE`.
- **Index name**: only known logical indexes (`jobs`, `applications`) are accepted by `SearchManager`.
- **Encoding**: input normalized to UTF-8/`utf8mb4`; Arabic input handled consistently (see Edge Cases).

## Edge Cases

1. **No active tenant** → `SearchManager` refuses to run a tenant search (fail closed); only `platform.*` paths may search without a `workspace_id`.
2. **Empty / too-short query** → return an empty result with a hint, or a simple recent/filtered list, rather than a full scan.
3. **MySQL FULLTEXT min token length** (`innodb_ft_min_token_size`, default 3) means very short tokens may not match; the driver falls back to a prefix `LIKE` on the indexed column for such tokens and documents the limitation.
4. **Stopwords** — common words may be ignored by FULLTEXT; boolean mode and the prefix fallback mitigate this for the buyer's default install.
5. **Arabic & RTL** — MySQL FULLTEXT tokenizes on whitespace and is diacritic-sensitive; the system normalizes Arabic (strip tatweel/diacritics where appropriate) before matching, and this is exactly the scenario that motivates the optional Meilisearch/Elasticsearch driver (better Arabic + typo tolerance).
6. **No results** → return an empty `SearchResult` (not an error) so the UI can show an empty state.
7. **Engine switch mid-flight** → after changing `config('search.driver')`, a reindex (`flush()` + rebuild) is required for external engines; MySQL needs none.
8. **Large tenants** → relevance + filter + pagination keep result sets bounded; very large/noisy queries are length- and page-limited; heavy tenants are the upgrade case for an external engine.
9. **Deleted/closed records** → filters (e.g. exclude `archived` jobs) are applied so search reflects the intended visibility.

## Security

- **Tenant isolation is the top search threat**; it is mitigated by injecting `workspace_id` inside `SearchManager` (clients cannot override it) and, for external engines, per-tenant indexes or mandatory `workspace_id` filters ([08-Multi-Tenant.md](08-Multi-Tenant.md)).
- **SQL injection** is prevented by always binding the query and filter values as parameters through `QueryBuilder` — even FULLTEXT `AGAINST(:q ...)` uses a placeholder.
- **No information leakage**: search honors per-record permissions/visibility, so a user cannot infer the existence of records they may not see.
- **DoS resistance**: minimum length, page caps, and (optionally) rate limiting on the search endpoint via `ThrottleRequests` prevent expensive query floods.
- **Output escaping**: result fields (titles, snippets) are escaped via `e()` in views; highlighted snippets never inject raw HTML.
- **External engine credentials** (Meilisearch/ES keys) are stored in `.env`/config, not in tenant data, and the app holds only what the deployment configures.

## Performance

- **FULLTEXT indexes** turn free-text search from an O(n) `LIKE '%...%'` scan into an index lookup; they are the core performance lever for the default driver.
- **Composite filter indexes** already on `jobs (workspace_id, status)` and `applications (workspace_id, job_id, status, current_stage_id)` let the `workspace_id`/status filters use indexes alongside the FULLTEXT match.
- **Pagination** (`LIMIT/OFFSET`) bounds every response; deep paging can later move to keyset pagination if needed.
- **Result/count caching**: identical (tenant, query, filters, page) tuples can be cached briefly to absorb repeated typing/paging; cache keys include `workspace_id` so tenants never share cached results.
- **Query budget**: search endpoints target a small, indexed query count (one search + one count, optionally cached); N+1 is avoided by joining `users` for candidate names instead of per-row lookups.
- **Offload at scale**: moving to Meilisearch/Elasticsearch removes search load from the primary MySQL, adds typo-tolerance/faceting, and scales horizontally — all behind the same `SearchInterface`.
- **Async indexing** for external engines runs through `queued_jobs` so writes are not slowed by index pushes.

## Testing

- **Unit**: `SearchManager` always injects the active `workspace_id` and ignores client-supplied tenant ids; query normalization and boolean-mode sanitization behave as specified; `min_query_length` fallback works.
- **Driver**: `MysqlFulltextDriver` builds a parameterized `MATCH … AGAINST` with the `workspace_id` filter and correct ordering/paging; future `Meilisearch`/`Elasticsearch` drivers satisfy the same `SearchInterface` contract tests.
- **Feature**: searching `jobs`/`applications` returns relevant, ranked, paginated results; filters (status/department/stage) narrow correctly; empty query returns a sensible empty/recent result.
- **Security/isolation**: a user in company A never receives company B's jobs/applications via search (including via the `users` join); search with no active tenant fails closed; injection-style queries are safely parameterized; result fields are escaped.
- **i18n**: Arabic queries return expected matches after normalization; RTL terms work; the documented FULLTEXT min-token/stopword limitations are covered by the prefix fallback test.
- **Engine swap**: switching the configured driver and reindexing yields equivalent tenant-scoped results.

## Future Expansion

- **Meilisearch / Elasticsearch drivers** behind the existing `SearchInterface` for typo-tolerance, synonyms, faceted filters, and much better Arabic handling — selected per deployment via `config('search.driver')`.
- **More searchable entities**: extend the searchable-column map to `candidates' profiles`, interview notes, and evaluations as those modules mature — no driver changes required.
- **Faceted/aggregated search** (counts by status/department/stage) once an engine that supports facets is in use.
- **Saved searches & alerts** (notify when a new application matches a recruiter's saved query) layered on `notifications`.
- **Suggestions/autocomplete** and "did you mean" powered by the external engine.
- **Per-tenant relevance tuning** (boost recent/open jobs) configurable without code changes.

## Open Questions

None at this time. The default driver, searchable fields, and tenant-scoping rules are fixed by the canonical context; the exact Arabic-normalization steps and the external-engine indexing schema will be finalized in `config/search.php` and the respective driver's implementation, and reflected back here and in [05-Database-Architecture.md](05-Database-Architecture.md).
