# FEATURE SPEC — Search

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Search · **Layer:** Platform Services · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Search module provides **unified, workspace-scoped search** across the
platform. It maintains a search index of indexable entities and answers queries
within the boundary of the active workspace. It is a cross-cutting **shared
service** that exists once and is consumed via its contract; modules MUST NOT
build their own search engines (`PROJECT_CONSTITUTION.md` §4, `MODULES.md` §4). In
Phase 9 the index covers **members, files, settings, roles, invitations, and the
workspace** itself; **recruitment entities (jobs, applications, candidates, …) are
added in Phase 10** when the Recruitment module ships. Search results are always
filtered by `workspace_id` — cross-workspace results are a critical defect.

## 2. Scope

**In scope**
- A unified index keyed by entity type, with workspace-scoped documents.
- Indexing via subscription to publishing modules' events (create/update/delete)
  and/or contract calls.
- Query/search with ranking, filtering by entity type, and pagination.
- Re-index / backfill operations for an entity type or a workspace.
- Phase-9 indexed types: **members, files, settings, roles, invitations,
  workspace**.

**Out of scope**
- Recruitment entity indexing — added in **Phase 10** with Recruitment (the
  contract is designed to accept new types additively).
- The source-of-truth data — owned by the publishing modules; Search stores only
  index documents/projections, never the authoritative record.
- Result-level authorization semantics beyond workspace scoping and permission
  filtering (the consuming surface still respects each module's view permissions).
- Analytics/reporting — owned by **Reports / Analytics**.

## 3. Inputs

- Index/update/delete signals from publishing modules (via events and/or contract).
- Search queries (text, entity-type filters, workspace context) from authenticated
  users.
- Re-index/backfill commands.

## 4. Outputs

- Ranked, paginated, workspace-scoped search results grouped/filterable by entity
  type.
- Index documents/projections (internal).
- Domain events in §7 (index lifecycle).

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Workspaces** — to resolve tenant context so every query and document is scoped
  by `workspace_id` (contract).
- **Permissions** — to filter results to what the requesting user may view
  (results respect each entity's view permission).
- **Event Bus** (shared) — to subscribe to indexable lifecycle events from other
  modules.
- **Memberships**, **Files**, **Settings**, **Permissions (roles)** — as **event
  sources** the index consumes; Search subscribes to their events rather than
  depending on their internals (`MODULES.md` §5).

Search depends only on shared/foundation modules and consumes others via events,
keeping the dependency graph acyclic. Publishing modules do not depend on Search.

## 6. Permissions (keys this module declares; resource.action grammar)

- `search.query` — perform a search within the active workspace.
- `search.reindex` — trigger a re-index/backfill (administrative).

Search enforces workspace scoping unconditionally and **additionally** filters
each result by the requesting user's view permission for that entity type (e.g. a
file result requires `files.view`; a member result requires `member.view`). Deny
by default; enforcement is server-side (`PERMISSION_MODEL.md` §5). System-wide
search administration, if any, is governed by a system permission.

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `search.index.updated`
- `search.index.rebuilt`
- `search.document.removed`

**Subscribed** (Phase 9 sources)
- `workspaces.workspace.created` / `workspaces.workspace.updated` /
  `workspaces.workspace.deleted`
- `memberships.membership.activated` / `memberships.membership.removed` /
  `memberships.invitation.created` / `memberships.invitation.cancelled`
- `files.file.uploaded` / `files.file.deleted`
- `settings.setting.updated`
- role lifecycle events from **Permissions** (roles indexed for search)

In Phase 10, Recruitment's events (jobs/applications/candidates) are added to this
subscription set without changing the Search engine.

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **Index Document** *(workspace-scoped projection)* — `workspace_id`, entity
  type, source entity reference (ULID), searchable text/fields, ranking/weight
  metadata, timestamps. This is a derived projection, never the source of truth.

Search owns only its index storage. Because IDs are app-generated ULIDs, an index
document stores a reference to the source entity without duplicating authoritative
data (`DATABASE_GUIDE.md`).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Every index document and every query is scoped by `workspace_id`; no
      cross-workspace result is ever returned.
- [ ] Phase-9 entity types — members, files, settings, roles, invitations,
      workspace — are indexable and searchable.
- [ ] Results are additionally filtered by the requesting user's view permission
      for each entity type.
- [ ] Indexing happens via subscribed events and/or the contract; Search never
      reads another module's tables directly.
- [ ] Results are ranked, filterable by entity type, and paginated (no unbounded
      queries, per `PROJECT_CONSTITUTION.md` §11).
- [ ] Re-index/backfill rebuilds the index for an entity type or workspace and
      emits `search.index.rebuilt`.
- [ ] Adding Recruitment entity types in Phase 10 requires no change to the Search
      engine — only additional subscriptions/registrations.
- [ ] `search.query` is required to search; deny by default, enforced server-side.
- [ ] Search depends only on shared/foundation modules and consumes sources via
      events, introducing no cycle.

### Related Documents
`MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `DATABASE_GUIDE.md` · `FEATURE_SPECIFICATIONS/Files.md` ·
`FEATURE_SPECIFICATIONS/Memberships.md`
