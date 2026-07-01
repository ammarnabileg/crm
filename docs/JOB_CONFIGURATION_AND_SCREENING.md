# Job Configuration, AI Screening & the Interview Pipeline

> The job is the single control surface for its hiring automation. Everything an
> employer wants the AI to do for a role is configured on the job; the candidate
> experience and the interview engine read that configuration. All AI runs use the
> **workspace's own keys** (BYO) — the platform is never billed for a screening.

This document is the source of truth for the per-job configuration, the
credit-saving AI-screening decision, the candidate application flow, the
adaptive interview engine, the avatar integration and the scoring/auto-rules.

---

## 1. Per-job configuration

Migrations `2026_06_30_000001_interview_screening` and
`2026_06_30_000002_job_configuration` add these columns to `jobs` (every column
is nullable or defaulted, so existing jobs keep their previous behaviour):

| Column | Type | Meaning |
|---|---|---|
| `ai_screening_enabled` | TINYINT(1) =1 | Master switch for the AI screening interview on this job. Off = applications are accepted with no AI interview. |
| `screening_keywords` | TEXT | Optional HR keywords for the deterministic, no-AI pre-screen. |
| `interview_required` | TINYINT(1) =1 | The interview is a required step (vs optional). |
| `interview_type` | VARCHAR(16) ='text' | `text` \| `voice` \| `avatar` (presentation of the room). |
| `avatar_id` | CHAR(26) | The linked AI-interviewer avatar (persona). NULL = the default strong-HR persona. |
| `required_skills` | TEXT | HR's must-have skills, used by the AI skills-match score. |
| `experience_min` / `experience_max` | INT | Desired experience window (years). |
| `passing_score` | INT (0–100) | Fit score at/above which the candidate auto-qualifies. |
| `auto_reject_score` | INT (0–100) | Fit score below which the candidate is auto-rejected. |
| `auto_advance_stage_id` | CHAR(26) | Pipeline stage qualified candidates are auto-moved into. |
| `interview_expiration_days` | INT | Days a scheduled interview stays open. |
| `max_attempts` | INT =1 | How many times a candidate may take the interview. |
| `interview_duration_minutes` | INT | Overrides the default 20-minute room window. |
| `questions_limit` | INT | Overrides the default 12-question budget. |
| `interview_start_mode` | VARCHAR(16) ='choice' | `immediate` \| `later` \| `choice` (start-now-or-later). |
| `deadline_at` | DATETIME (UTC) | Application deadline (pre-existing column; now used for the countdown + entry guard). |

Migration `2026_06_30_000003_first_impression_engine` adds the two **First
Impression** controls (both defaulted, so existing jobs are unchanged):

| Column | Type | Meaning |
|---|---|---|
| `first_impression_enabled` | TINYINT(1) =0 | Opt-in switch for the zero-AI First Impression gate that runs **between Apply and the paid AI interview**. Off by default. |
| `min_first_impression_score` | INT =65 | The First Impression credibility score (0–100) at/above which an applicant proceeds to the AI interview; below it the applicant is saved with status `filtered_pre_ai` and HR may override. |

> The First Impression gate is **fully rule-based and spends zero AI credits** —
> it is the credit-saving layer that sits *before* the AI screening described in
> this document. See **`FIRST_IMPRESSION_ENGINE.md`** for the scoring model, the
> normalised report tables, the HR override and the analytics. When both are on,
> the order is: **Apply → First Impression (no AI) → AI Screening interview**.

And `applications.available_from` (VARCHAR 255) — the candidate's "when can you
start" answer captured at apply time.

### New application status

`filtered_pre_ai` ("Filtered Before AI") — set when First Impression scores an
applicant below `min_first_impression_score`. The application, candidate, CV and
full report are still saved; only the AI interview is withheld until an HR
override (`pipeline.manage`). The value is a plain string on
`applications.status` (no ENUM), added to `ApplicationStatus::STATUSES`.

**Staff UI** — `JobsController` (`create`/`store`/`edit`/`update`) renders the
shared fieldset `resources/views/recruitment/jobs/_config_form.php` on the create
and edit pages and a read-only summary + avatar panel on the job page
(`show.php`). `JobService::update()` persists every column above via its
whitelist; `JobController::jobConfig()` normalises and clamps the inputs.

---

## 2. The application flow (career → portal)

```
Career page / public job  ─►  Apply  ─►  choose/upload CV  ─►  "When can you start?"
        │                                                              │
        └────────────────────────────  Submit  ──────────────────────┘
                                            │
                          ScreeningService decision (no AI spend)
                                            │
                  ┌────────────────────────┴───────────────────────┐
              run AI interview                                  accept, no AI
                  │                                                    │
        start mode: immediate ─► room   |   later/choice ─► application detail
                  │                                                    │
              Interview  ─►  Completion  ─►  scoring + auto-rules  ─►  Candidate Portal
```

- **Two apply paths** converge: `PublicJobController::apply` (tokenized public
  page) and `CandidatePortalController::apply` (in-portal `/open-jobs`). Both
  capture `available_from`, run the screening decision, refuse new applications
  past `deadline_at`, and honour `interview_start_mode`.
- **CV gate** (`InterviewRoomService::needsCv`/`attachCv`): before a not-yet-started
  interview, the candidate must pick a library CV or upload a new one
  (`portal/interview_cv.php`, `POST /interview/{id}/cv`).
- **Deadline / expiration guard** (`CandidatePortalController::room`): a
  not-yet-started interview can't be entered after `deadline_at` or after
  `created_at + interview_expiration_days`. Resuming an in-progress interview
  always works.

---

## 3. AI screening — the credit-saving decision

`ScreeningService::shouldRunAiInterview($ws, $job, $userId, $coverNote)` is the
single decision used by both apply paths. AI runs only on the **client's keys**
(`AiCapabilities::interviewsEnabled` = an OpenAI key exists for the workspace):

| Condition | Result |
|---|---|
| `ai_screening_enabled = 0` | No AI interview (accept, handle manually). |
| Workspace has no AI key | No AI interview (cannot run one). |
| Keywords set **and no match** in the applicant's words | No AI interview (accept — **save credits**). |
| Enabled **and** (no keywords **or** a keyword match) | Run the AI interview. |

The applicant's words = cover note + their workspace CV profile (summary +
structured skills/education/etc.) + name (`CvScreening::keywords/hits/passes`,
pure & unit-tested).

**Avatar fallback at scheduling**: `interview_type = avatar` schedules a video
room only when HeyGen is configured (`AiCapabilities::videoEnabled`), otherwise
text — automatic, no error.

---

## 4. The interview engine

`InterviewRoomService` runs a turn-by-turn room. With a real provider each turn
is generated **live** (`interview_turn` capability) and grounded in the
candidate's CV/profile + the job's description, requirements and weighted
criteria — it builds on the candidate's last answer, presses on vague answers,
never repeats, and stays in persona. With only the offline echo provider it
falls back to the planned/static question bank.

- **Progress counts ANSWERED questions.** An unanswered question, or an
  "Ask a different question" turn (`action=change`), never consumes the budget
  and never ends the interview.
- **Per-job overrides**: `durationMinutes()` (← `interview_duration_minutes`,
  default 20) and `questionsLimit()` (← `questions_limit`, default 12).
- **Persona** (`persona()`): default is a strong, professional senior-HR voice.
  When a job links an avatar, the avatar's personality (`ai_avatars.style_notes`,
  then `prompt`, then `persona`) drives the interviewer; unlinking restores the
  default.

---

## 5. Avatar ↔ job integration

- **Link / replace**: `POST /jobs/{id}/avatar` (`JobsController::linkAvatar`,
  perm `job.update`) sets `jobs.avatar_id` + `interview_type='avatar'`.
- **Remove (✕)**: `POST /jobs/{id}/avatar/remove` clears the link and reverts to
  `interview_type='text'` — the AI returns to its default behaviour.
- **Preview**: links to the existing `/avatars/{id}/preview`.
- Only **active** avatars are pickable (`AvatarService::listActive`). The job page
  shows the provider status and a HeyGen-fallback hint when relevant.

---

## 6. Scoring, recommendation & auto-rules

On completion `InterviewRoomService::finalize` stores the transcript and runs
`AssessmentService::assessFromInterview`, which derives the fit score from **real
signal**, not a placeholder:

1. The provider's own `SCORE: NN` when a real model returns one; else
2. a deterministic match of the job's `required_skills` + `screening_keywords`
   against the transcript + the candidate's CV (skills matching), blended with the
   competency baseline; else
3. the competency baseline alone.

The matched/missing skills surface as strengths/weaknesses and a `cv` breakdown;
the model's narrative becomes the summary. Then `applyAutoRules` enforces the
job's thresholds (best-effort; a human always overrides):

- `fit < auto_reject_score` → application set to **disqualified**.
- `fit >= passing_score` → moved to **`auto_advance_stage_id`** (or set to
  **qualified** when no stage is configured).

Status/stage changes are written within the Recruitment module with the normal
history trail (`application_status_history` / `application_stage_history`).

---

## 7. Candidate experience (portal)

`portal/application.php` leads with a **Start-now / Resume** banner whenever an AI
interview is pending and the deadline hasn't passed, including a live countdown
(and a closed state once it passes). It also shows: next step, a progress
stepper (current stage + timeline), the candidate's "can start" date, the AI
summary, the interview list (with start/continue actions), offers and withdraw.
Public job and in-portal job lists show a per-role deadline countdown and the
"When can you start?" field on apply.

---

## 8. Routes, permissions & verification

**New routes** (all in `RecruitmentModule`):
`POST /jobs/{id}/avatar`, `POST /jobs/{id}/avatar/remove`,
`POST /interview/{id}/cv`.

**Permissions**: no new keys — avatar link/unlink reuse `job.update`; the CV gate
and screening run inside the candidate gate. (No permission-catalog drift.)

**Views**: `recruitment/jobs/_config_form.php` (required by create + edit),
`portal/interview_cv.php` (rendered by `room()`); the enhanced `jobs/show.php`,
`portal/jobs.php`, `recruitment/public_job.php`, `portal/application.php`,
`portal/interview.php`. No orphan views.

**Backward compatibility**: every new column is nullable/defaulted;
`ApplicationService::apply` gained a trailing defaulted `availableFrom`;
`InterviewRoomService` keeps its 3-argument constructor (auto-rules use
same-module SQL, not a new dependency), so no existing call site or test breaks.
