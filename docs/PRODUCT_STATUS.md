# HaHireAI — Product Status Report

_AI-native, multi-tenant recruitment platform — native PHP 8.3+ modular monolith,
MySQL 8, server-rendered, no framework._

## The model (constitutional)

- **User is the origin, not Role.** One `users` identity. There are exactly two
  actor kinds: a **System Owner** (a user with `system.*`) and a **User**.
- **Workspace is a workspace** — not a role, not an account. A user relates to a
  workspace as **staff** (a membership with a role) or as a **candidate**
  (applied, no role). The same person can be staff in one workspace and a
  candidate in another.
- **Roles are data**, built per workspace (like Notion/Jira/GitHub) — never
  hardcoded. The owner gets every workspace permission by direct grant.
- **One dynamic sidebar**, built from `(current user + workspace + permissions +
  context)`. Three contexts: `workspace` (staff), `candidate`, `platform`.
- **Candidate Profile is per-workspace** (Microsoft profile ≠ Google profile) for
  privacy.
- **AI is advisory** — it analyzes and recommends; a human always decides.

## Feature inventory

### Staff workspace
| Area | Status | Notes |
|---|---|---|
| Dashboard | ✅ | dynamic sidebar from permissions |
| Jobs: list / create / **edit** / **archive** | ✅ | seniority intern→executive, salary band |
| Job **question bank** (#4) | ✅ | feeds the AI interview room in order |
| Job **criteria / rubric** (#2) | ✅ | weighted dimensions |
| Candidates: list / search / compare | ✅ | advanced search by score/skill |
| Candidate file (HR decision center) | ✅ | AI assessment, decision bar, timeline, files |
| Pipeline (Kanban) | ✅ | 11 statuses; **HTML5 drag-&-drop** + bulk move |
| AI Interviews | ✅ | advisory, scored |
| Human Interviews | ✅ | schedule online/onsite + structured 1–5 evaluation, reschedule/archive |
| Offers | ✅ | send / decline / **withdraw** + **printable letter (PDF)** |
| Talent Pool | ✅ | saved candidates |
| Avatars | ✅ | AI interviewer avatars |
| Reports | ✅ | funnel + **print (PDF)** + native **Excel (.xlsx)** export |
| AI analytics | ✅ | usage & tokens per workspace |
| Members / Roles | ✅ | roles as data; member **suspend/reactivate/remove** + last login/activity; role **clone/delete** + usage count |
| Settings | ✅ | general + company / branding (**logo upload**) / **SMTP email** / **legal** / security / maintenance |
| AI settings | ✅ | provider, encrypted keys, interview mode (text/video) |
| Notifications / Activity / Search / Files | ✅ | notifications: **categories, search, archive** |
| Diagnostics (platform) | ✅ | 8 read-only infra panels (DB/storage/cache/queue/mail/SSL/workers/runtime) |
| Billing | ✅ | **free period when no gateway connected** |
| Workflows / Integrations | ✅ | event-driven automations + API gateway |

### Candidate portal (applied, no role)
| Page | Status | Notes |
|---|---|---|
| Candidate Portal overview | ✅ | application status, interviews, offers, latest jobs |
| Available jobs + apply | ✅ | **search + filters** (type/seniority/location); **CV select/upload** (PDF/Word) |
| AI interview room — **text (mode A)** | ✅ | chat, question counter, 20-min timer, resumable, auto-close, completion screen |
| AI interview room — **voice (mode B)** | ✅ | browser STT **or server-side OpenAI Whisper** using the workspace's own key |
| AI interview room — **video avatar (mode C)** | 🟡 | mounts where a HeyGen key is set; not exercised offline |
| My applications + detail | ✅ | stage map, AI notes, "next step", accept/decline/**counter-offer**, **withdraw** |
| My profile + CV library | ✅ | name/phone/experience/target salary + CVs |
| Workspace chooser | ✅ | where you work → dashboard; where you applied → portal; create |

### AI agent
| Capability | Status |
|---|---|
| CV analysis | ✅ |
| 11-skill weighted evaluation | ✅ |
| Personality (DISC / Big Five) | ✅ |
| Red flags with severity | ✅ |
| Recommendation bands (82+/68–81/50–67/<50) | ✅ |
| Candidate comparison + AI Q&A | ✅ |
| Never decides — human decides | ✅ |

### Platform (System Owner)
Overview · Workspaces · Users · Subscriptions · Audit logs · Diagnostics — ✅

## Architecture (ARCHITECTURE.md §4)

- Modules communicate only through **Core contracts** + events — never another
  module's internal classes. Shared services exposed as contracts:
  `UserDirectory`, `AuditRecorder`, `CandidateDirectory`, `RecruitmentSnapshot`,
  `EntitlementResolver`, `FileStorage`, `AccessControl`, `MemberDirectory`.

## Quality

- **184 tests / 707 assertions** green on live MySQL 8.
- **Production auditor: 87/87** checks (`bin/certify.php`), including contract-
  resolution and permission-catalog↔DB parity.
- Verified end-to-end over real HTTP: login → portal → apply → AI interview room
  → completion → scored; offer accept → hired; staff vs candidate routing.
- **43 full-page screenshots** of every page in `docs/screenshots/`.

## Honest gaps / next

- **Live-video avatar (HeyGen, mode C)** is wired and gated but cannot be
  exercised in this environment (no outbound to HeyGen).
- The standalone **tokenized interview link** (`/interview/{token}`) still uses
  the older one-shot flow; the **job-link path** already converges on the new
  conversational room.
