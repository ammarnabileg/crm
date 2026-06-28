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
| Pipeline (Kanban) | ✅ | 11 statuses; move via action |
| AI Interviews | ✅ | advisory, scored |
| Human Interviews | ✅ | schedule online/onsite + structured 1–5 evaluation, reschedule/archive |
| Offers | ✅ | send / decline / **withdraw** + **printable letter (PDF)** |
| Talent Pool | ✅ | saved candidates |
| Avatars | ✅ | AI interviewer avatars |
| Reports | ✅ | funnel + **print (PDF)** + **CSV (Excel)** export |
| AI analytics | ✅ | usage & tokens per workspace |
| Members / Roles | ✅ | roles as data |
| Settings | ✅ | general + **company / branding / security / maintenance mode** |
| AI settings | ✅ | provider, encrypted keys, interview mode (text/video) |
| Notifications / Activity / Search / Files | ✅ | |
| Billing | ✅ | **free period when no gateway connected** |
| Workflows / Integrations | ✅ | event-driven automations + API gateway |

### Candidate portal (applied, no role)
| Page | Status | Notes |
|---|---|---|
| Candidate Portal overview | ✅ | application status, interviews, offers, latest jobs |
| Available jobs + apply | ✅ | **CV select/upload** (PDF/Word) |
| AI interview room — **text (mode A)** | ✅ | chat, question counter, 20-min timer, resumable, auto-close, completion screen |
| AI interview room — **voice (mode B)** | 🟡 | browser speech-to-text over the same engine |
| AI interview room — **video avatar (mode C)** | 🟡 | mounts where a HeyGen key is set; not exercised offline |
| My applications + detail | ✅ | stage map, AI notes, "next step", accept/decline/**counter-offer** |
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

## Quality

- **155 tests / 550 assertions** green on live MySQL 8.
- **Production auditor: 65/65** checks (`bin/certify.php`).
- Verified end-to-end over real HTTP: login → portal → apply → AI interview room
  → completion → scored; offer accept → hired; staff vs candidate routing.
- **43 full-page screenshots** of every page in `docs/screenshots/`.

## Honest gaps / next

- **Live-video avatar (HeyGen, mode C)** is wired and gated but cannot be
  exercised in this environment (no outbound to HeyGen).
- The standalone **tokenized interview link** (`/interview/{token}`) still uses
  the older one-shot flow; the **job-link path** already converges on the new
  conversational room.
- **Excel** export is CSV (opens in Excel); not native `.xlsx`.
- **Branding** covers colour/tagline/company; a **logo upload** is not yet wired.
- Pipeline stage change is via action, not HTML5 drag-and-drop.
