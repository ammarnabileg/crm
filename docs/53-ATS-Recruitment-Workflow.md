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

## Backward compatibility
Additive only — no recruitment table, relationship or service was renamed or removed;
the migration is idempotent and guarded.
