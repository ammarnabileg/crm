# REQUIRES APPROVAL — Legacy / Cleanup Backlog

> **Status:** Open · **Last updated:** 2026-07-01
> Produced by a full legacy / dead-code / duplication audit (parallel domain
> audits + grep-verified evidence). **Nothing in this file has been deleted.**
> Each item is here because it is either **data-bearing**, **still referenced**,
> part of a **contract**, or carries **≥1% doubt** — per the standing rule, those
> are reported, not removed. Items that were 100%-proven-unused were already
> removed (see CHANGELOG “Removed — verified-dead code”).

---

## 1. Data-bearing — need a data decision before any drop

### 1.1 `candidate_profiles.details` JSON column — migration IN PROGRESS
- **State now:** the normalised `candidate_profile_fields` table exists, is
  **backfilled** from every existing `details` map, and `saveDetails()`
  **dual-writes** both; keyword search already reads the normalised table.
- **Remaining before the column can be dropped (needs approval):** move the last
  JSON readers onto `fields()` —
  `AssessmentService.php:289`, `InterviewRoomService.php:481`,
  `ScreeningService.php:63`, `CandidatesController.php:151,318`,
  `CandidatePortalController.php:666`, and the `CAST(details AS CHAR)` fallback in
  `CandidateProfileService::searchByKeyword`. Then a drop-column migration.
- **Impact of dropping prematurely:** breaks Decision-Center detail reads, the
  screening keyword pre-check, and CV-brief building. **Do not drop yet.**

### 1.2 Orphan auth tables — `user_sessions`, `password_resets`, `remember_tokens`
- Created by `2026_06_27_000002_create_auth_tables.php`; **referenced by zero code**
  (auth uses native PHP `$_SESSION`, `app/Core/Http/Session.php`).
- **Doubt:** data-bearing + they look like a planned DB-session / password-reset /
  remember-me design. Dropping could lose rows and/or pre-empt a planned feature.
- **Recommendation:** confirm the feature is abandoned, then drop in one migration.

### 1.3 Old subscriptions/plans “dead island” (Billing)
- `BillingService` and `SubscriptionLifecycle` are **never instantiated in
  production** (no `new`/container binding in `app/ bin/ bootstrap/`) — superseded
  by the wallet/`workspace_plans` system. BUT they are **test-covered**
  (`BillingTest`, `SubscriptionLifecycleTest`, `ReleaseCertificationTest`) and
  operate the data-bearing `subscriptions`/`invoices` tables, and the still-live
  `EntitlementResolverAdapter` falls back to `Entitlements`/`SubscriptionService`
  when a workspace has no wallet plan.
- **Recommendation:** treat as a deliberate legacy compatibility path. Removing it
  means retiring the fallback + those tests + a data decision on `subscriptions`/
  `invoices`. **Needs approval.**

---

## 2. Contract / behavioral — safe-looking but risky

### 2.1 `Middleware` contract (`app/Core/Contracts/Middleware.php`)
- Zero implementers. **But its own docblock says the execution pipeline is “wired
  in a later phase.”** Intentional forward-looking stub → keep unless that plan is
  abandoned.

### 2.2 Unused permission gates (21 keys)
Enforced nowhere (never reach `->can()`): `workspace.update/settings/archive/
restore/delete/transfer`, `member.update`, `permission.assign`, `candidate.export`,
`job.delete`, `application.view/update`, `workflow.update/delete/execute/publish/
pause/logs/variables/settings/templates` (the Workflow UI collapses the 9 granular
workflow perms into `workflow.view`/`create`). `workspace.view` is sidebar-only.
- **Doubt:** these are a **seeded permission catalog** (a contract), referenced by
  role tests; removing a gate key can silently change future authorization.
- **Recommendation:** either wire the gates (preferred — they represent real
  actions) or remove from the catalog in a deliberate, tested change. **Approval.**

---

## 3. Low-risk removable public methods (awaiting approval)

Grep-verified zero call sites, `final` classes, not interface members. Kept because
they are plausibly-intended API/utilities and removal is a judgment call:

`RateLimiter::purgeOlderThan`, `ApiContext::authenticated`,
`SocialAdapterRegistry::keys`, `InvoiceService::markVoid`, `PlanService::publicPlans`,
`PricingCatalog::unitPrice`, `PlanComposer::monthlyCost`,
`BillingService::freePeriodDays`, `PermissionRepository::count`,
`MembershipService::countForWorkspace`, `ActivityFeed::countForWorkspace`,
`WorkspaceContext::membershipId`, `Installer::unlock`, `PasswordHasher::needsRehash`,
`UserResumeService::countForUser`, `UserSocialProfileService::urls`,
`FirstImpressionReportService::latestForApplication`,
`CvScreening::secondsUntilDeadline`.

> Note: Learning service methods flagged by an early sweep (`updateSection`,
> `updateItem`, `CommentService::edit`) are **no longer dead** — they were wired to
> the builder UI (see CHANGELOG). `ItemType::icon/values`, `TodoStatus::label` are
> kept as API-consistent enum-catalog helpers (mirrors `ProgramStatus::label`,
> which is used).

---

## 4. Duplicate logic — consolidation candidates (refactor, not deletion)

No behavior change intended; each is a “merge into one helper” opportunity:
- **`{{token}}` resolver** — identical in `WorkflowEngine` and `ActionExecutor`
  (drifted 3rd copy in `PromptEngine`).
- **`nextPosition()`** (`COALESCE(MAX(position),-1)+1`) — repeated across
  `ProgramService`, `QuizService`, `TodoService`, `LearningPathService`,
  `InterviewRoomService`, `JobContentService`.
- **Slug builders** — `WorkspaceCreator`, `JobService`, `ProgramService`,
  `LearningPathService`, `WorkflowCollectionService`.
- **`safeName()`** filename sanitiser — `FileService` vs `UserResumeService`.
- **`SCORE: NN` parse+clamp** — `AssessmentService` vs `InterviewService`.
- **`guessMime` maps** — `UserResumeService` vs `FileService`.
- **Token-cost const/formula** — `AiEngine` vs `FirstImpressionAnalyticsService`.

> These are cross-module in places; consolidating means choosing a shared home
> (likely `app/Support` or a Core helper) without creating a new coupling. Worth
> doing, but as a focused, separately-reviewed refactor. **Approval to proceed.**

---

### How to action this file
Tell me which numbered items to execute; each becomes its own tested, reversible
change (migrate readers → drop column; wire or remove gates; extract a shared
helper). Nothing here changes until approved.
