# FEATURE SPEC — Files

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** Files · **Layer:** Platform Services · **Implemented in:** Phase 9
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The Files module is the **shared file-storage service** of HaHireAI. It provides
upload, folder organization, storage abstraction, ownership, visibility, preview,
download, soft delete, and retention for workspace-scoped files. It is a
cross-cutting capability that exists **once** and is consumed via its contract;
modules MUST NOT re-implement file storage (`PROJECT_CONSTITUTION.md` §4,
`MODULES.md` §4). Files is **domain-agnostic** — it is not tied to recruitment;
any module (resumes, branding assets, attachments) stores files through this
service. Files are workspace-scoped with explicit ownership and visibility
(`DOMAIN_MODEL.md` §4.4).

## 2. Scope

**In scope**
- Upload of files with server-side validation (type, size, content scanning).
- Folder hierarchy for organizing files within a workspace.
- Storage abstraction (files stored **outside the web root**) with a pluggable
  backend (local/object storage).
- Ownership (owning `User` and owning module/entity reference) and **visibility**
  (e.g. private / workspace-visible) controls.
- Preview generation/reference and authenticated download.
- Soft delete (`deleted_at`) and retention policy enforcement.

**Out of scope**
- Business semantics of any file (e.g. that a file is a "resume") — owned by the
  consuming module, which references the file by ULID.
- Virus-scanner implementation/transport — requested as a capability; Files
  orchestrates scanning but does not embed a third-party provider.
- Search indexing internals — owned by **Search** (Files emits events Search
  consumes).
- Long-term archival/backup of storage media — owned by **Observability**
  (Phase 15).

## 3. Inputs

- Upload requests (binary/stream, filename, content type, target folder, owner,
  visibility) from authenticated members.
- Folder create/rename/move commands.
- Download and preview requests (by file ULID).
- Soft-delete and restore commands.
- Retention configuration (read from **Settings**: storage policy).

## 4. Outputs

- Persisted `File` records with ULID identity, storage reference, checksum, size,
  content type, owner, visibility, and folder.
- Authenticated download streams and preview references.
- Storage-usage figures per workspace (for display and, later, entitlement checks).
- Domain events in §7.

## 5. Dependencies (modules + contracts consumed; shared services used)

- **Workspaces** — to resolve tenant context for `workspace_id` scoping (contract).
- **Users** — to resolve file owners (contract).
- **Permissions** — for authorization of upload/delete/view actions.
- **Settings** (shared service) — to read storage policy and retention windows.
- **Audit** (shared service) — to record uploads, deletions, and access where required.
- **Search** (shared service) — to index file metadata for unified search.
- **Notifications** (shared service) — OPTIONAL, for retention/expiry notices.

Files MUST NOT depend on recruitment or other business modules; they depend on
Files. This keeps the dependency graph acyclic.

## 6. Permissions (keys this module declares; resource.action grammar)

- `files.view` — view/list files and folders (subject to visibility).
- `files.upload` — upload a file / create a file record.
- `files.download` — download file content.
- `files.delete` — soft-delete a file.
- `files.manage` — manage folders and retention/visibility settings.

Visibility further restricts access beyond the permission check: a private file is
visible only to its owner (and explicitly authorized consumers) even when the
viewer holds `files.view`. Deny by default; all enforcement is server-side, and
hiding UI is never a substitute (`PERMISSION_MODEL.md` §5).

## 7. Events (Published / Subscribed)

**Published** (`module.entity.event`, past tense)
- `files.file.uploaded`
- `files.file.downloaded`
- `files.file.deleted` (soft)
- `files.file.restored`
- `files.folder.created`
- `files.file.retention_expired`

**Subscribed**
- `workspaces.workspace.deleted` — mark the tenant's files inactive alongside the
  soft-deleted workspace (no hard delete; recoverable).

## 8. Data Owned (conceptual entities only — defer detail to DATABASE_ARCHITECTURE.md, Phase 3)

- **File** *(workspace-scoped)* — `workspace_id`, owner `user_id`, optional owning
  module/entity reference, filename, content type, size, checksum, storage
  reference, visibility, folder reference, `deleted_at`, timestamps.
- **Folder** *(workspace-scoped)* — `workspace_id`, name, parent-folder reference,
  owner, timestamps.

Binary content is stored outside the web root via the storage abstraction; the
database holds metadata and a storage reference only. Soft-delete uses `deleted_at`
and is recoverable; retention expiry is a distinct policy outcome
(`DATABASE_GUIDE.md` §9).

## 9. Acceptance Criteria (checklist of testable outcomes)

- [ ] Every file carries `workspace_id`; files are never visible across workspaces.
- [ ] Uploads are validated (type, size) and content-scanned before becoming
      available; binaries are stored outside the web root.
- [ ] `files.upload` is required to upload and `files.delete` to soft-delete; both
      are denied without the permission, enforced server-side.
- [ ] Visibility rules restrict access beyond the permission check (private files
      are not exposed to non-owners).
- [ ] Folders form a per-workspace hierarchy; files can be organized and moved
      between folders.
- [ ] Soft delete sets `deleted_at` and is recoverable; the default read path
      excludes soft-deleted files.
- [ ] Retention policy (from Settings) removes/flags files past their window and
      emits `files.file.retention_expired`.
- [ ] Each listed action emits its event in §7 and is recorded in Audit where
      required.
- [ ] Downloads are authenticated and authorized; direct web access to stored
      binaries is not possible.

### Related Documents
`MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `SECURITY_GUIDE.md` · `DATABASE_GUIDE.md` ·
`FEATURE_SPECIFICATIONS/Search.md` · `FEATURE_SPECIFICATIONS/Settings.md`
