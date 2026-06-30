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
| **Wallet billing (per workspace)** | ✅ | prepaid USD wallet + ledger; owner free, extra staff = billable seats |
| **Plan composer** (seats + features) | ✅ | live cost, mandatory pre-activation review; 1-month term; auto-renew from wallet |
| **Add-ons** | ✅ | charged now, expire with the plan |
| **Fawaterak top-up** | ✅ | iframe + signed idempotent webhook; offline simulate when unconfigured |
| **Locked state** | ✅ | unfunded renewal blocks staff except the billing page (`billing.manage`) |
| **Platform pricing catalog** | ✅ | seat + feature prices, System Owner (`system.pricing.manage`) |
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
| **White Label / Branding Center** | ✅ | one source of truth (`BrandingService`): colours, **typography**, logo; gated by `white_label` feature + `workspace.branding` |
| AI settings | ✅ | provider, encrypted keys, interview mode (text/video) |
| **AI Provider Profiles** | ✅ | named encrypted {provider, model, key} profiles with a single default |
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
Overview · Workspaces · Users · Subscriptions · Pricing · Audit logs · Diagnostics — ✅

## Architecture (ARCHITECTURE.md §4)

- Modules communicate only through **Core contracts** + events — never another
  module's internal classes. Shared services exposed as contracts:
  `UserDirectory`, `AuditRecorder`, `CandidateDirectory`, `RecruitmentSnapshot`,
  `EntitlementResolver`, `WorkspaceSeatGuard`, `FileStorage`, `AccessControl`,
  `MemberDirectory`.

## Quality

- Production auditor (`bin/certify.php`) covers contract-resolution and
  permission-catalog↔DB parity, run on a fresh MySQL 8 schema before any test
  teardown.
- Verified end-to-end over real HTTP: login → portal → apply → AI interview room
  → completion → scored; offer accept → hired; staff vs candidate routing.
- **43 full-page screenshots** of every page in `docs/screenshots/`.

## Verification status (workspace-billing + White Label branch)

Honest record of what has and has not been re-confirmed on this branch:

- ✅ **Workspace wallet billing** (wallet ledger, plan composer, seats, add-ons,
  Fawaterak top-up + signed webhook, auto-renew, locked state, platform pricing
  catalog, seat guard) ran **green on live MySQL 8 in CI** — migrate + seed +
  tick + certify + the targeted wallet/seat-guard suite, then the full suite.
- ⏳ The later increments on this branch — the commercial **“Build Your
  Workspace”** page, **AI Provider Profiles**, the **platform UX layer**, and the
  **White Label centralisation (`BrandingService`)** — are pushed and reviewed by
  construction (additive, backward-compatible, no schema/route/contract breaks)
  but their CI run has **not yet been read back to confirmation** in the current
  working environment. They are **not** claimed as test-verified until that
  re-run is confirmed green.

## Honest gaps / next

- **Live-video avatar (HeyGen, mode C)** is wired and gated but cannot be
  exercised in this environment (no outbound to HeyGen).
- The standalone **tokenized interview link** (`/interview/{token}`) still uses
  the older one-shot flow; the **job-link path** already converges on the new
  conversational room.
- White Label captures a `radius` (corner-radius) preference but the app's
  Tailwind radius utilities are not yet remapped onto it; colour and typography
  are applied app-wide today.
