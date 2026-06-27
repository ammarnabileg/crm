# 53 — ATS & Recruitment Workflow

> The Applicant Tracking System is the **core engine** of HalaOps. The AI Interview
> Engine ([51](51-AI-Interview-Engine.md)) is one stage *within* the ATS, not the
> other way around. The platform manages the full recruitment cycle — from publishing
> a job to hiring (and onboarding/archiving) the employee.

## Related Documents
- [24 — Job Lifecycle](24-Job-Lifecycle.md), [25 — Application Lifecycle](25-Application-Lifecycle.md)
- [51 — AI Interview Engine](51-AI-Interview-Engine.md) (interview stages)
- Workflow Automation Engine + Plugin SDK (extensibility) — see [CHANGELOG](CHANGELOG.md)
- [08 — Multi-Tenant](08-Multi-Tenant.md), [38 — Audit System](38-Audit-System.md)

## Purpose
Manage thousands of jobs and millions of applications efficiently, flexibly and
multi-tenant-isolated — a professional ATS, not a jobs page.

## Architecture
Built on the **existing** recruitment schema (the Database Bible already shipped
`jobs`, `applications`, `pipelines`, `pipeline_stages`, `offers`, `offer_approvals`,
`schedules`, `meetings`, `pools`, `candidate_profiles`, `notes`, `status_histories`,
`departments`, `teams`). The ATS adds a **service layer** (additive only — one
migration, `0045`, adds `applications.assigned_recruiter_id`/`assigned_interviewer_id`
and a `tasks` table; `scheduled_tasks` remains the CRON scheduler). Each service is
thin, tenant-scoped through `App\Core\Model` (fail-closed), and emits domain events.

### Services (`App\Services\Ats`)
| Service | Responsibility |
|---|---|
| `JobManager` | Job lifecycle: create → publish → pause → close → archive (`job_statuses` + timestamps + events). |
| `PipelineManager` | Per-job pipelines; `createDefault()` seeds the standard 11-stage pipeline; add/reorder/clone/assign. |
| `ApplicationFlow` | Apply (lands on the initial stage); `moveToStage` (mirrors status, writes a `status_histories` audit row, emits events); assign recruiter/interviewer. |
| `InterviewScheduler` | Schedule/reschedule/cancel `meetings` for interviews. |
| `OfferManager` | Offer lifecycle: create → approve → send → accept (advances to *hired*) / decline (`offer_statuses`). |
| `RejectionManager` | Reject with a reason (+ template), set status `rejected`, log history + note, emit `candidate.rejected`. |
| `TalentPool` | Save candidates into pools/groups (`pools`, `pool_candidates`). |
| `AdvancedSearch` | Search candidates/applications by job/status/score/source/stage/recruiter + profile attributes (skills/experience/availability/salary). |
| `CandidateTimeline` | Merge applications, status changes, interviews+meetings, notes and offers into one chronological timeline. |
| `TaskManager` | Recruiter to-dos linked polymorphically to job/candidate/interview; assign/complete. |
| `AtsEvents` | Bridges every ATS change into the Workflow Automation Engine. |

## Recruitment pipeline (default)
Each job has its own editable pipeline. The seeded default:

`Applied → CV Screening → AI Screening → HR Interview → Technical Interview →
Manager Interview → Final Interview → Offer → Hired → Onboarding → Rejected → Archived`

Each `pipeline_stages` row carries name, description, color, order, the
`application_status_id` it maps to, a `stage_type_id`, and initial/terminal/passed
flags. Pipelines are create/edit/reorder/clone/assignable per company.

## Application lifecycle
`applied → in_review → interviewing → offer → hired` (terminal), with `rejected` /
`withdrawn` as terminal exits. `applications.current_stage_id` tracks the pipeline
position; `application_status_id` mirrors the stage's status. Every move is recorded
in `status_histories` (who/when/from→to/note).

## Automation Ready
Every meaningful change emits a domain event consumed by the Workflow Automation
Engine: `job.published`, `job.closed`, `application.submitted`, `application.reviewed`,
`candidate.passed`, `candidate.rejected`, `interview.scheduled`, `offer.created`,
`offer.accepted`, `offer.declined`. Tenants attach automations (conditions + actions)
with no code.

## The AI Interview Engine as an ATS stage
The "AI Screening" / interview stages drive the [AI Interview Engine](51-AI-Interview-Engine.md):
the State Machine runs the interview, the 9-agent layer + Decision Engine produce an
explainable report, and the result feeds the application's evaluation/decision —
all within the candidate's ATS journey.

## Multi-tenancy, audit & permissions
- **Tenant isolation:** every ATS table carries `workspace_id` and is fail-closed
  scoped; cross-tenant access is impossible.
- **Audit:** stage/status moves write `status_histories`; mutations write
  `activity_logs` (who/when/device/what changed) — see [38](38-Audit-System.md).
- **Permissions:** every ATS action is permission-gated (not role-only), following
  [11 — Permissions Matrix](11-Permissions-Matrix.md) / the RBAC constitution; UI
  controllers enforce `can(...)` before invoking these services.

## Self-validation
The full lifecycle is exercised end-to-end by `tests/Feature/AtsWorkflowTest.php`:
Create Job → Publish → Apply → Review/Move → Assign Recruiter → Schedule Interview →
Run AI Interview → Generate Report → Create Offer → Accept Offer → Reject another →
Archive Job — all green.

## Web layer (dashboard, no terminal)
Shipped on top of the service layer (`App\Controllers\Ats`, gated by
`recruitment.view`/`recruitment.manage`): the **Recruiter Workspace** dashboard,
**Jobs** list + create (with the default pipeline) + publish/close/archive, the
**Kanban pipeline board** (applications grouped by stage with a server-rendered move),
and the **application detail + candidate timeline**. Nav entries are permission-gated;
forms carry CSRF; verified by `tests/Feature/AtsWebTest` (each page renders 200).

Remaining UI polish (additive, no schema change): drag-and-drop + bulk actions on the
board, per-role dashboards (HR manager / department manager / owner), offer PDF +
e-signature, calendar export, and rejection email delivery.

## Public Careers portal (the candidate-facing front door)
The **unauthenticated** pages where the general public browses a company's openings
and applies — `App\Controllers\Public\CareersController` + `App\Services\Careers\CareersService`,
views `resources/views/public/careers/{index,show,applied}` (the public `guest`
layout, not the app shell):

- **Routes (no auth, no tenant):** `GET /careers/{workspace}` (the company's open
  roles), `GET /careers/{workspace}/{job}` (a role + apply form), `POST
  /careers/{workspace}/{job}/apply` (CSRF-protected + rate-limited `throttle:5,60`).
  The workspace is resolved from the public **slug**, never from session/tenant.
- **What's exposed:** ONLY an **active** (or trialing) workspace's **published**
  (`job_statuses.open` + a `published_at`) non-deleted jobs. Drafts/paused/closed
  jobs, suspended/unknown workspaces, and any **other** workspace's jobs are never
  reachable — they 404, even by URL tampering (every read in `CareersService` is
  filtered explicitly by the slug-resolved `workspace_id`; it is intentionally not
  tenant-scoped because there is no logged-in tenant). Salary shows only when
  `is_salary_public`.
- **Applying:** find-or-create the candidate by email (the single `users` table — a
  candidate is a `User`, mirroring `MemberDirectory`), optional CV via the existing
  `FileService` (same mime/size/path-traversal guards), then the existing
  `ApplicationFlow::apply` (initial pipeline stage + `application.submitted` event),
  with `source = career_site`. The controller sets the tenant to the slug-resolved
  workspace **before** the write so the tenant-scoped `Application`/`File` rows are
  stamped with the correct `workspace_id`. Re-applying to the same job is idempotent
  (reported as a duplicate — no second row, no enumeration of whether the email
  pre-existed).
- **Discoverability:** the internal **Jobs** page carries a "View public careers
  page" link to the workspace's `/careers/{slug}` so an owner can find and share it.
- **Verified:** `tests/Feature/CareersWebTest` (10 — published-only listing, draft
  hidden, cross-workspace job 404, suspended/unknown workspace 404, apply creates
  user+application scoped to the right workspace, idempotent re-apply, can't apply to
  a draft) + a live HTTP smoke (browse 200, 404 isolation, CSRF apply → application
  landed with `source=career_site`, tokenless POST rejected 419).

## Backward compatibility
Additive only — no recruitment table, relationship or service was renamed or removed;
the migration is idempotent and guarded.
