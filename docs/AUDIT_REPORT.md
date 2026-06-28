# HaHireAI — Enterprise Audit & Gap Analysis

> Method: verified against the actual code (routes, controllers, views, services,
> migrations) by four parallel domain audits + objective probes. Nothing is marked
> PASS unless a route + handler + view was found. The `/docs` constitution is the
> reference. **This is an honest report — the implemented system is a strong,
> well-architected subset of the enterprise spec, not 100% of it.**

## Objective baseline (verified)
- **Tests:** ✅ **155 / 550 assertions passing** (re-confirmed after a MySQL restart;
  the transient 112-error run was MySQL crashing mid-suite, an infra flake, not code).
- **Auditor:** `bin/certify.php` **65/65**.
- **Surface:** 132 routes, 55 tables.
- **Single User model:** ✅ PASS — `users` is the only identity; **no** `candidates`/
  `recruiters`/`hr`/`owner` tables. Difference is membership + roles + permissions +
  ownership + applications only.

## Functional end-to-end journey (verified over real HTTP) — ✅ 0 CRITICAL
The complete critical path runs green (`scratchpad/e2e.sh`, **13 PASS / 0 CRITICAL**):
register → **career page** → **apply** (converges on the interview room) → **AI
interview room** greeting → 12 answers → **auto-complete** → **candidate portal** →
**application detail** → owner **dashboard** → **pipeline stage move** (→ qualified) →
owner **sends offer** → candidate **counter-offer** → candidate **accepts** → **hired**.
**No journey-breaking (CRITICAL) defects.** The gaps below are depth/compliance, not breaks.

## Severity legend
`EXISTS` (works) · `PARTIAL` · `MISSING` · `BROKEN` · `REFACTOR` (works but violates the constitution)

---

## 1. Architecture compliance

| Rule | Status | Evidence |
|---|---|---|
| AI only via AI Engine | ✅ EXISTS | all `->ai->run()`; no provider SDK outside AiEngine |
| Events via Event Bus | ✅ EXISTS | EventDispatcher; reactors subscribe |
| Integrations via Integration Platform | ✅ EXISTS | WebhookDispatcher + CurlHttpClient |
| Automation via Workflow Engine | ✅ EXISTS | WorkflowEngine centralizes |
| No business logic in controllers | 🟡 PARTIAL | raw SQL in `CandidatesController::index` (77-83) and `OffersController` (109-111) → REFACTOR |
| **Modules talk only via Contracts/Events** | ❌ REFACTOR | **constitutional violation, pervasive & pre-existing.** `ARCHITECTURE.md §4` mandates a module's public surface is its `Contracts/` + events; the code injects concrete Application services across modules (AuditLogger, MembershipService, Authorizer, JobService, …) and even another module's **Infrastructure** (`Users\Infrastructure\UserRepository` from Authentication ×2 and CandidatePortalController ×1). |

**The sharpest, bounded fix (BLOCKER per the constitution):** cross-module
**Infrastructure** imports of `UserRepository` (3 sites). Fixable with a small Core
contract (`UserDirectory`). The broader "everything via Contracts" is real debt but
a large refactor (~11 modules need published contracts + rewired DI).

---

## 2. Workspace sidebar — page-by-page

| Page | Status | What exists | Missing vs spec |
|---|---|---|---|
| **Dashboard** | ❌ MISSING (depth) | access/workspaces/account cards | All 14 spec widgets: KPIs, open/closed jobs, applicants, new/needs-review, today's interviews, my tasks, recent activity/jobs, AI recommendations, subscription status, workspace health, quick actions |
| **Jobs** | 🟡 PARTIAL (~43%) | create, edit, archive, publish, public career page, public link, AI interview link, applications count, pipeline, question bank, criteria | pause, clone, preview, share, search, filters, bulk actions, job analytics, hiring team, timeline |
| **AI Interviews** | 🟡 PARTIAL (~36%) | status, evaluation, provider, cost | search, filters, replay, per-interview report, Excel export, duration, transcript view |
| **Pipeline** | 🟡 PARTIAL (~29%) | Kanban, move via form | drag-&-drop, bulk move, filters, saved views, automation, custom-stage UI |
| **Candidates** | 🟡 PARTIAL (~75%) | search-by-skill, filters (score/band/skill), tags, AI score, stage, open, compare | full-text search, bulk actions, export |
| **Candidate Profile (Decision Center)** | 🟡 PARTIAL (~50%) | overview, AI assessment+recommendation, 11 skills, interviews, human interview, notes, files/CV, offers, tags, talent pool, timeline, change stage, add note/document | resume parser, interview video/transcript, shared notes, stage history, experience/education/languages/certificates, current/expected salary, availability, location, contacts, emails, tasks, scorecards, audit history |
| **Human Interviews** | 🟡 PARTIAL (~64%) | create, schedule online/onsite, meeting link, reschedule, structured 1–5 evaluation, archive | calendar, notes field, recording, transcript, dedicated feedback |
| **Offers** | 🟡 PARTIAL (~64%) | create, send, withdraw, accept, counter (candidate), PDF/print | manager approve step, manager reject, history/audit view, email send |
| **Talent Pool** | 🟡 PARTIAL (~25%) | pools, save candidate (future) | smart lists, passive/rejected groups, pool tags, search, bulk actions |
| **Avatars** | 🟡 PARTIAL (~44%) | library, persona, language, basic voice flag | prompt, knowledge, greeting, status toggle, preview, testing (currently ~CRUD) |
| **Users / Members** | 🟡 PARTIAL (~40%) | name, email, status, roles, **invite**; workspace-scoped (not system users) ✅ | phone, per-member permissions, last login/activity, joined date, suspend/activate/deactivate/remove/transfer |
| **Roles & Permissions** | 🟡 PARTIAL (~44%) | create, permission matrix, permission groups, roles-as-data ✅ | clone, delete, "users using this role", search, export, audit |
| **Billing** | 🟢 MOSTLY (~82%) | current plan, usage, invoices, renewal, limits, upgrade/downgrade, cancel, free period when no gateway | payment methods, coupons |
| **My Workspaces** | 🟢 MOSTLY | list with role, members, plan, status, switch; totals (total/active/suspended) | logo, last-activity per row |
| **Workspace Settings** | 🟡 PARTIAL (~60%) | general, localization, company, branding (color/tagline), security (2FA flag/session), maintenance | address, contact, email/SMTP, phone, date format, **logo/favicon upload**, domain, legal |
| **AI Settings** | 🟡 PARTIAL (~55%) | providers, encrypted API keys, model, fallback, interview mode | prompt library/versions, budget, language, limits, streaming |
| **Diagnostics** | 🟡 PARTIAL (~15%) | health probes, read-only ✅ | DB/storage/queue/mail/cache/permissions/cron/SSL/AI/API/workers/performance panels |
| **Maintenance** | 🟡 PARTIAL (~63%) | enable/disable, message, enforced in shell ✅ | allowed IPs, read-only mode, logs, history |

> Note: an earlier sub-audit wrongly flagged Members/Roles/AI/Billing/Search as
> "missing" because it only searched the Workspaces module — I verified all of those
> routes EXIST and render 200 (they live in their own modules).

**Staff UI depth overall: ~50% of the deep spec** (72 PASS + 6 partial of ~176 sub-features).

---

## 3. Candidate experience (Feature 14)

| Page | Status | Missing |
|---|---|---|
| Candidate Home (`/portal`) | 🟡 PARTIAL | profile-completion, tasks, on-home notifications/timeline |
| Careers page | 🟡 PARTIAL | search, filters, departments/locations, share, SEO |
| AI Interview Room — **Text** | ✅ EXISTS | counter, timer, autosave, resume, auto-end, completion screen — all present |
| AI Interview Room — **Voice** | 🟡 PARTIAL | speech-to-text present; no review/retry step |
| AI Interview Room — **Avatar** | ❌ MISSING | no video avatar / camera / recording (HeyGen gated, not built) |
| Applications | 🟡 PARTIAL | timeline, messages, **withdraw**; accept/reject/counter present |
| Application Details | 🟡 PARTIAL | candidate-visible notes, documents, full timeline; stage map/next-step/AI summary/offers present |
| Candidate Profile (self) | 🟡 PARTIAL | education, languages, skills, certificates, portfolio, social, availability, privacy; personal+salary+experience+multi-CV present |
| Notifications | 🟡 PARTIAL | categories, archive, search |
| Context gating (candidate-only sidebar) | ✅ EXISTS | resolves correctly; staff never see portal |

---

## 4. Docs compliance
✅ Mostly compliant (no FAILED/BLOCKER). 3 WARNINGs: `FEATURE_SPECIFICATIONS/Workspaces.md`
overstates branding (logo/favicon) as in-scope; `MODULES.md` lists Settings and
Reports/Analytics as standalone modules though implemented inside Workspaces/Recruitment.

---

## 5. Final classification (PASS / PARTIAL / FAILED / CRITICAL)

### CRITICAL (breaks the user journey)
**None.** The full Career→Apply→Interview→Portal→Pipeline→Offer→Accept journey runs green.

### PASS (matches spec)
Single-User model · RBAC isolation (System Owner `system.*` only) · AI-only-via-Engine ·
Events-via-Bus · Integrations-via-Platform · Automation-via-Workflow · candidate **text**
interview room (counter/timer/autosave/resume/auto-end/completion) · apply→room convergence
(both paths) · offers send/withdraw/accept/counter + PDF · billing free-period · roles-as-data ·
My Workspaces · certify 65/65 · 155 tests green.

### PARTIAL (works, lacks spec depth)
Dashboard (cards, not the 14 widgets) · Jobs (no pause/clone/preview/share/search/filters/bulk/
analytics/timeline) · AI Interviews (no search/filters/replay/report/export/duration/transcript) ·
Pipeline (no drag-drop/bulk/saved-views/automation/custom-stage UI) · Candidates (no full-text/
bulk/export) · Candidate Profile (missing ~18 fields: parsed CV, video/transcript, education,
languages, certificates, salary, availability, stage history, emails, tasks, scorecards, audit) ·
Human Interviews (no calendar/notes/recording/transcript) · Offers (no approve/reject/email/history) ·
Talent Pool (no smart lists/passive/bulk) · Avatars (no prompt/knowledge/greeting/preview/test) ·
Users (no phone/last-login/suspend/remove/transfer) · Roles (no clone/delete/users-using/export/audit) ·
Settings (no logo/favicon/domain/email-SMTP/address/legal) · AI Settings (no prompt library/budget/
streaming) · Diagnostics (health only, ~2/13 probes) · Maintenance (no allowed-IPs/read-only/logs) ·
Candidate voice interview (no review/retry) · Careers (no search/filters/SEO) · Notifications
(no categories/archive/search).

### FAILED (constitution / not built)
- **Architecture — module communication not via Contracts** (pervasive, pre-existing; `ARCHITECTURE.md §4`).
  Sharpest, bounded sub-item = cross-module **Infrastructure** import of `UserRepository` (3 sites).
- **Avatar interview mode** (video/HeyGen) — gated, not built.
- **Workspace lifecycle:** archive / restore / transfer-ownership — not implemented.

### WARNING
Raw SQL in 2 controllers · doc overstatements (branding scope; Settings/Reports module placement).

## Verdict (honest)
**CRITICAL: 0 · Architecture ~70% · Docs ~95% · RBAC 100% · User model 100% · Workspace UI
depth ~50% · Candidate portal core complete (depth ~55%) · Tests green (155).**
The core product is sound and the end-to-end journey works. It is **not 100%** of the
enterprise spec — closing PARTIAL+FAILED is a sizable, multi-feature **build phase**, not a
bug-fix pass. Recommended order: bounded architecture BLOCKER → workspace lifecycle →
Dashboard widgets → Candidate Profile depth → page-by-page depth.
