# HaHireAI — Screen captures

Full-page screenshots of every page, captured against a live server with seeded
data and the locally built Tailwind stylesheet (`bin/build-assets.sh`). Regenerate
the styling with that script; the captures here are a point-in-time reference.

The pages a user sees are decided by **context**, never by a fixed role:
- **Staff** (a member with a role) sees the permission-driven workspace sidebar.
- **Candidate** (applied, no role) sees the Candidate Portal.
- **System Owner** sees the platform admin.

## Guest
| File | Page |
|---|---|
| `login.png` | Sign in |

## Staff workspace (member with full permissions)
| File | Page |
|---|---|
| `staff-dashboard.png` | Dashboard (dynamic sidebar from permissions) |
| `staff-jobs.png` | Jobs list |
| `staff-job-detail.png` | Job detail — question bank + criteria/rubric + interview links |
| `staff-job-edit.png` | Edit job |
| `staff-candidates.png` | Candidates (search + compare) |
| `staff-candidate-file.png` | Candidate file — AI assessment, decision bar, timeline |
| `staff-pipeline.png` | Pipeline (Kanban, 11 statuses) |
| `staff-ai-interviews.png` | AI Interviews |
| `staff-human-interviews.png` | Human Interviews (schedule + list) |
| `staff-human-interview-detail.png` | Human interview — structured 1–5 evaluation |
| `staff-offers.png` | Offers (send / withdraw / print) |
| `staff-offer-print.png` | Printable offer letter (Save as PDF) |
| `staff-talent-pool.png` | Talent Pool |
| `staff-avatars.png` | AI interviewer avatars |
| `staff-reports.png` | Reports — hiring funnel |
| `staff-reports-print.png` | Printable report (Save as PDF) |
| `staff-members.png` | Members |
| `staff-roles.png` | Roles (data, built per workspace) |
| `staff-settings.png` | Workspace settings — company / branding / security / maintenance |
| `staff-ai-settings.png` | AI settings — provider, keys, interview mode |
| `staff-ai-analytics.png` | AI analytics — usage & tokens |
| `staff-notifications.png` | Notifications |
| `staff-billing.png` | Billing (free period when no gateway) |
| `staff-my-workspaces.png` | My Workspaces |
| `staff-search.png` | Search |
| `staff-files.png` | Files |
| `staff-workflows.png` | Workflows |
| `staff-integrations.png` | Developer / integrations |
| `staff-activity.png` | Activity / audit |

## Candidate portal (applied, no role)
| File | Page |
|---|---|
| `cand-portal-home.png` | Candidate Portal overview |
| `cand-portal-jobs.png` | Available jobs (apply + CV) |
| `cand-portal-applications.png` | My applications + offers |
| `cand-portal-application-detail.png` | Application detail — stage map, AI notes, next step |
| `cand-portal-interview-room.png` | AI interview room (chat, counter, timer, voice) |
| `cand-portal-profile.png` | My profile + CV library |
| `cand-workspaces-select.png` | Choose a workspace to enter |

## Platform (System Owner)
| File | Page |
|---|---|
| `platform-admin-overview.png` | Platform overview |
| `platform-admin-workspaces.png` | All workspaces |
| `platform-admin-users.png` | All users |
| `platform-admin-subscriptions.png` | Subscriptions |
| `platform-admin-audit.png` | Audit logs |
| `platform-admin-diagnostics.png` | Diagnostics |
