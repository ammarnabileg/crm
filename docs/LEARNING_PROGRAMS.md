# LEARNING PROGRAMS — HaHireAI

> **Status:** Adopted · **Version:** 1.0.0 · **Last updated:** 2026-06-30
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`, `MODULES.md`,
> `PERMISSION_MODEL.md`, `DATABASE_ARCHITECTURE.md`.

---

## 0. Purpose & naming

The **Learning** module is a workspace-owned platform for **training, development
and onboarding** programs — the place a company builds *Company Culture*,
*Software Developer Onboarding*, *HR Onboarding*, *Sales Training*, *Security
Awareness*, *Performance Improvement* or *Leadership* programs and runs its people
through them with full progress tracking.

> **Naming.** The module is named **`Learning`** (sidebar: *Learning* for authors,
> *My Learning* for learners). It was proposed as "Learning Programs"; the shorter
> *Learning* reads better in the nav and scales to training/onboarding/development
> without implying a single artifact type. The namespace is `HaHireAI\Modules\Learning`.

It is **zero-AI** by construction today (no provider is ever called); the
architecture leaves room for an optional AI assist later via the AI Engine, never
inline.

## 1. Where it sits

A first-class module in the modular monolith (`config/modules.php`), depending on
**Workspaces** (tenancy + shell), **Memberships** (assignment fan-out via the
`MemberDirectory` contract) and **Files** (document items / attachments). It
communicates with other modules **only** through:

- the **`LearningCatalog`** Core contract (its public read + enrol surface), and
- the **Event Bus** (`learning.*` events the Workflow Engine can react to).

It never reads another module's tables; role fan-out goes through
`MemberDirectory::membersWithRole()` (added to that contract for this module).

## 2. Domain model

```
Program ─┬─ Tags
         ├─ Editors (collaboration: owner|editor|viewer)
         ├─ Versions (snapshot history)
         ├─ Sections ─── Items (lesson|video|document|link|task|todo_list|quiz|note)
         │                  └─ Quiz scaffold (questions → options)
         ├─ To-dos (self|manager mode) ── Status history
         ├─ Comments (polymorphic: program|section|item|todo) ── Mentions
         ├─ Attachments (polymorphic)
         ├─ Assignments (user|role|department|team)
         └─ Enrollments ── Item progress
Activity timeline (per entity)
```

**Program** carries: title, slug, summary, description, cover, category, tags,
difficulty (beginner/intermediate/advanced), estimated minutes, status
(draft/published/archived), **completion rule** (all_items / required_items /
percentage + threshold), version, and `visibility` (the seam for future
cross-workspace sharing — `workspace` today).

## 3. Lifecycle & collaboration

- **Draft → Published → Archived.** Publishing makes the program assignable;
  archiving retires it without losing enrollments or history.
- **Editors.** The creator is the `owner`; more editors can be added
  (owner/editor/viewer) so several staff can co-author a program.
- **Version history.** "Snapshot" stores the full structure as an archival JSON
  row in `learning_program_versions` and bumps the program version. (The snapshot
  is a deliberate archival artifact — the only JSON in the module; live data is
  fully normalised.)

## 4. To-dos — the two modes

Every to-do has a **completion mode**:

- **`self`** — the assignee (or a supervisor) marks it done.
- **`manager`** — only a supervisor (`learning.todo.manage`) can move it to
  *done*; the assignee may progress it but **never close it**.

Each has a due date, priority (low/normal/high/urgent), assignee, status
(open/in_progress/done/blocked) and a full **status history** trail. The guard is
the pure `TodoStatus::canComplete()`.

## 5. Comments — everywhere

A **polymorphic** comment thread hangs off any **program / section / item / todo**
(`learning_comments.entity_type` + `entity_id`). Threads support **replies**
(`parent_id`), **@mentions** (`learning_comment_mentions`), **edit**, and
permission-aware **soft delete** (author or a manager). Attachments are supported
via the polymorphic `learning_attachments` table.

## 6. Assignment, enrollment & progress

- **Assign** a program to a **user / role / department / team**. Assignment **fans
  out** to per-user **enrollments** (user → that user; role → all active members
  with the role via `MemberDirectory`; department/team are recorded and resolve to
  members once those org structures exist).
- **Progress** is per-item (`learning_item_progress`) and rolled up by the pure
  `ProgressCalculator` against the program's completion rule into a percent +
  status (not_started / in_progress / completed). Self-directed learners
  auto-enrol on first interaction.
- A **roster + stats** dashboard (enrolled / completed / in-progress / avg %) is on
  the program page; each learner sees their own **My Learning** with progress bars.

## 6b. LMS capabilities — certificates, prerequisites, paths

- **Certificates.** Completing a program issues a **certificate**
  (`learning_certificates`, one per learner per program, idempotent) with a
  verifiable serial; learners see them on *My Learning* and open a printable
  certificate page (`/my-learning/certificate/{serial}`). Issued automatically in
  `EnrollmentService` when an enrollment reaches *completed*.
- **Prerequisites.** A program can require other programs first
  (`learning_prerequisites`); the learner reader shows the unmet prerequisites,
  and authors manage them from the program page.
- **Learning paths (tracks).** An ordered sequence of programs
  (`learning_paths` + `learning_path_programs`) with its own draft/published
  lifecycle and per-learner path progress (share of its programs completed).
  Surfaced at `/learning-paths`.

## 6c. Paid feature (Billing add-on)

Learning is a **paid add-on** (`learning` in `FeatureCatalog`), **not** part of
the basic plan. Every Learning route — authoring, learner, and paths — passes a
`FeatureGate` that allows access only when the workspace's composed plan enables
the `learning` feature (or when billing is disabled / there is no plan, so the
platform still works out of the box). The sidebar items are feature-gated too.
**Disabling the add-on never deletes data** — it only blocks access until the
subscription re-enables it, at which point every program, enrollment and
certificate reappears. The architecture leaves room for a free tier / trial
(the gate is a single check, easily relaxed per plan).

## 7. Permissions (catalog)

| Key | Grants |
|---|---|
| `learning.view` | View programs + own enrollments (My Learning) |
| `learning.manage` | Create/edit programs, sections, items, to-dos |
| `learning.publish` | Publish / archive / version programs |
| `learning.assign` | Assign programs & manage enrollments |
| `learning.todo.manage` | Complete manager-controlled to-dos / manage any to-do |

The sidebar shows **Learning** and **My Learning** to anyone with `learning.view`.

## 8. Events (Event Bus → Workflow triggers)

| Event | When | Workflow trigger |
|---|---|---|
| `learning.program.assigned` | a program is assigned | *Learning Program Assigned* |
| `learning.enrollment.created` | a learner is enrolled | *Learning Enrollment Created* |
| `learning.program.completed` | a learner completes a program | *Learning Program Completed* |

## 9. Data model (tables)

Workspace-scoped, ULID PKs, FK-constrained, indexed:
`learning_programs`, `learning_program_tags`, `learning_sections`,
`learning_items`, `learning_todos`, `learning_todo_status_history`,
`learning_comments`, `learning_comment_mentions`, `learning_attachments`,
`learning_assignments`, `learning_enrollments`, `learning_item_progress`,
`learning_program_editors`, `learning_program_versions`,
`learning_quiz_questions`, `learning_quiz_options`, `learning_activity`.

Migration: `database/migrations/2026_06_30_000004_learning_module.php`.

## 10. Candidate / employee integration (architecture only)

The `LearningCatalog` contract exposes `publishedPrograms()`, `countPrograms()`
and `enrollUser()` so a future Employees/Onboarding flow — or Recruitment, after a
candidate is hired — can enrol a person into onboarding programs **without
touching the Learning tables**. The seam is in place; the cross-module trigger is
not wired yet (a deliberate, documented next step).

## 11. Quizzes (implemented)

A `quiz` item holds **questions** (`learning_quiz_questions`) each with **options**
(`learning_quiz_options`); single / multiple / boolean types are supported.
Learners take the quiz from the reader; grading is the pure, zero-AI
`QuizGrader` (a question scores when the selected option set exactly matches the
correct set). Each submission records an **attempt** (`learning_quiz_attempts`) +
its **answers** (`learning_quiz_answers`); on reaching the item's **pass mark**
(`learning_items.pass_mark`, default 70%) the quiz item is marked complete and
feeds program progress. Best-attempt is shown back to the learner.

## 11b. What is scaffolded (architecture present, UI minimal)

- **Department / team assignment** — accepted and recorded; member resolution
  awaits those org structures.
- **Cross-workspace sharing** — `programs.visibility` is the seam; not enabled.
- **Version restore** — snapshots are captured; one-click restore is a next step.

## 12. UX

Simple, non-technical-friendly, in the platform's existing visual language
(Tailwind, server-rendered, progressive-enhancement forms — no SPA): a card
catalog, an inline program **builder** (sections → add-content), a clean learner
**reader** with one-click *Mark done*, and lightweight inline forms for to-dos and
discussion. Inspired by Notion/ClickUp/Trello clarity without leaving the
HaHireAI shell.

---

### Related Documents
`MODULES.md` · `ARCHITECTURE.md` · `PERMISSION_MODEL.md` ·
`DATABASE_ARCHITECTURE.md` · `WORKFLOW_ENGINE.md` · `SIDEBAR_MODEL.md`
