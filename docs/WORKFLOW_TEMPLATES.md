# WORKFLOW_TEMPLATES — HaHireAI

Ready-made workflows users can clone with one click from `/workflows/templates`.
Each is a real node graph in `app/Modules/Workflow/Domain/WorkflowTemplates.php`
(pure data). "Use this template" saves it as a **new, disabled** workflow and opens
it in the builder so the user reviews, tweaks, then enables it.

## The 10 templates

| Key | Trigger | Does |
|---|---|---|
| `ai-screen` | Candidate applied | AI-summarise the candidate, create a review task |
| `welcome-applicant` | Candidate applied | Notify the applicant, log the activity |
| `auto-reject-low` | Interview finished | If AI score < 50 → reject + notify |
| `fast-track-high` | Interview finished | If AI score ≥ 85 → advance + alert the team |
| `interview-followup` | Interview finished | Create a task to review the interview |
| `offer-accepted-onboarding` | Offer accepted | Onboarding task + welcome notification |
| `declined-to-pool` | Offer declined | Add to a talent pool + audit |
| `rejected-to-pool` | Candidate rejected | Add to a nurture pool |
| `score-to-collection` | Interview finished | Formula a score label → store a record (exportable) |
| `payment-failed-alert` | Payment failed | Notify + record for follow-up |

## Guarantees (tested)

`WorkflowTemplatesTest` asserts every template: uses only real `NodeCatalog` node
types, compiles to ≥ 1 executable step, and validates clean. Keys are unique and
`find()` resolves them.

Templates are built from the same nodes as any hand-made workflow, so anything a
template does is editable in the builder. See `WORKFLOW_NODES.md` and
`WORKFLOW_ENGINE.md`.
