# PERMISSION CATALOG — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PERMISSION_MODEL.md`. **Companions (Phase 4):**
> `SYSTEM_PERMISSIONS.md`, `WORKSPACE_PERMISSIONS.md`, `SECURITY_MATRIX.md`,
> `ACCESS_POLICIES.md`, `ROLE_BUILDER.md`, `AUDIT_EVENTS.md`.

This is the **single authoritative registry of every permission key** in the
system. Each module declares its permissions in its manifest; they are aggregated
here. Code checks **keys**, never role names. Categories are **display-only** and
never affect enforcement. Grammar: `resource.action` (workspace) and
`system.<area>.<action>` (platform). Deny-by-default.

---

## 1. Baseline capabilities (not workspace permissions)

Some actions are available to **any authenticated `User`** and are therefore not
gated by a workspace permission:

| Capability | Notes |
|---|---|
| `workspace.create` | Any user may create a workspace; they become its owner. |
| `workspace.join` | Any user may accept an invitation to join a workspace. |
| apply to a public job | Any user may apply (creates an `Application`). |
| manage own profile / sessions | A user manages their own identity. |

Everything else requires an explicit permission below.

## 2. Workspace permissions

### Workspace
| Key | Allows | Module |
|---|---|---|
| `workspace.view` | View workspace overview | Workspaces |
| `workspace.update` | Edit workspace core fields | Workspaces |
| `workspace.settings` | Manage workspace settings | Settings |
| `workspace.branding` | Manage logo/colors/branding | Workspaces |
| `workspace.archive` | Archive the workspace | Workspaces |
| `workspace.restore` | Restore an archived workspace | Workspaces |
| `workspace.delete` | Soft-delete the workspace | Workspaces |
| `workspace.transfer` | Transfer ownership | Workspaces |

### Members
| Key | Allows | Module |
|---|---|---|
| `member.view` | View members | Memberships |
| `member.invite` | Invite members | Memberships |
| `member.invite.resend` | Resend invitations | Memberships |
| `member.invite.cancel` | Cancel invitations | Memberships |
| `member.update` | Update a member (roles/status) | Memberships |
| `member.suspend` | Suspend a member | Memberships |
| `member.reactivate` | Reactivate a member | Memberships |
| `member.remove` | Remove a member | Memberships |

### Roles & Permissions
| Key | Allows | Module |
|---|---|---|
| `role.view` | View roles | Permissions |
| `role.create` | Create a role | Permissions |
| `role.update` | Rename/edit a role | Permissions |
| `role.clone` | Clone a role | Permissions |
| `role.delete` | Delete a role | Permissions |
| `permission.assign` | Assign permissions to roles / members | Permissions |

### Jobs
| Key | Allows | Module |
|---|---|---|
| `job.view` | View jobs | Recruitment |
| `job.create` | Create a job | Recruitment |
| `job.update` | Edit a job | Recruitment |
| `job.publish` | Publish a job | Recruitment |
| `job.pause` | Pause a published job | Recruitment |
| `job.close` | Close a job | Recruitment |
| `job.archive` | Archive a job | Recruitment |
| `job.delete` | Soft-delete a job | Recruitment |
| `job.clone` | Duplicate a job | Recruitment |
| `job.share` | Manage public link / share | Recruitment |

### Candidates
| Key | Allows | Module |
|---|---|---|
| `candidate.view` | View candidate profiles (workspace view) | Recruitment |
| `candidate.note` | Add/view notes | Recruitment |
| `candidate.tag` | Apply tags | Recruitment |
| `candidate.tag.manage` | Create/manage tag definitions | Recruitment |
| `candidate.rate` | Add ratings/scorecards | Recruitment |
| `candidate.export` | Export candidate data | Recruitment |

### Applications & Pipeline
| Key | Allows | Module |
|---|---|---|
| `application.view` | View applications | Recruitment |
| `application.update` | Update an application | Recruitment |
| `application.reject` | Reject an application | Recruitment |
| `application.withdraw` | Record withdrawal | Recruitment |
| `pipeline.view` | View the pipeline | Recruitment |
| `pipeline.manage` | Move stages, edit stages, bulk actions | Recruitment |

### Interviews
| Key | Allows | Module |
|---|---|---|
| `interview.view` | View interviews | Recruitment |
| `interview.schedule` | Schedule interviews | Recruitment |
| `interview.ai.run` | Run an AI interview (capability) | Recruitment → AI Engine |
| `interview.evaluate` | Submit evaluations/scorecards | Recruitment |
| `interview.cancel` | Cancel interviews | Recruitment |

### Offers & Employees
| Key | Allows | Module |
|---|---|---|
| `offer.view` | View offers | Recruitment |
| `offer.create` | Create an offer | Recruitment |
| `offer.update` | Edit a draft offer | Recruitment |
| `offer.send` | Send an offer | Recruitment |
| `offer.revoke` | Revoke an offer | Recruitment |
| `employee.view` | View employees | Recruitment |
| `employee.create` | Create employee (on hire) | Recruitment |
| `employee.update` | Update employee | Recruitment |

### Talent Pool & Templates
| Key | Allows | Module |
|---|---|---|
| `talent.view` | View talent pool | Recruitment |
| `talent.manage` | Manage talent pool/smart lists | Recruitment |
| `template.view` | View templates | Recruitment |
| `template.manage` | Manage templates | Recruitment |

### Platform services
| Key | Allows | Module |
|---|---|---|
| `report.view` | View reports/analytics | Reports |
| `report.export` | Export reports | Reports |
| `files.view` | View files | Files |
| `files.upload` | Upload files | Files |
| `files.download` | Download files | Files |
| `files.delete` | Delete files | Files |
| `files.manage` | Manage folders/visibility | Files |
| `notifications.view` | View notifications | Notifications |
| `notifications.manage` | Manage preferences/mark/clear | Notifications |
| `search.use` | Use unified search | Search |
| `audit.view` | View workspace audit log | Audit |
| `audit.export` | Export workspace audit log | Audit |
| `settings.view` | View settings | Settings |
| `settings.update` | Update settings | Settings |

### AI (workspace)
| Key | Allows | Module |
|---|---|---|
| `ai.view` | View AI settings/usage | AI Engine |
| `ai.run` | Invoke AI capabilities | AI Engine |
| `ai.configure` | Configure provider/model/limits | AI Engine |
| `ai.keys.manage` | Manage workspace API keys | AI Engine |
| `ai.prompts.manage` | Manage workspace prompts | AI Engine |

### Commerce (workspace)
| Key | Allows | Module |
|---|---|---|
| `billing.view` | View billing/invoices/usage | Billing |
| `billing.manage` | Manage plan/payment methods | Billing |

### Integration (workspace)
| Key | Allows | Module |
|---|---|---|
| `integration.view` | View integrations | Integration Platform |
| `integration.manage` | Connect/configure integrations | Integration Platform |
| `apikey.manage` | Create/rotate/revoke API keys | Integration Platform |
| `webhook.manage` | Manage webhooks | Integration Platform |

### Workflow (workspace)
| Key | Allows | Module |
|---|---|---|
| `workflow.view` | View workflows/executions | Workflow Engine |
| `workflow.create` | Create a workflow | Workflow Engine |
| `workflow.update` | Edit a workflow | Workflow Engine |
| `workflow.delete` | Delete a workflow | Workflow Engine |
| `workflow.publish` | Publish a workflow | Workflow Engine |
| `workflow.execute` | Manually trigger | Workflow Engine |
| `workflow.approve` | Act on approval steps | Workflow Engine |

## 3. System permissions (`system.*` — System Owners only)

| Key | Allows | Module |
|---|---|---|
| `system.dashboard.view` | Access Platform Context | System Administration |
| `system.users.manage` | Manage all users | System Administration |
| `system.workspaces.manage` | Manage all workspaces (suspend/resume/license) | System Administration |
| `system.subscriptions.manage` | Manage subscriptions/revenue | Subscriptions |
| `system.plans.manage` | Manage plans & coupons | Subscriptions |
| `system.settings.manage` | Manage platform settings | System Administration |
| `system.ai.manage` | Manage global AI providers/models | AI Engine |
| `system.integrations.manage` | Manage global connectors | Integration Platform |
| `system.diagnostics.run` | Run diagnostics | Observability |
| `system.observability.view` | View platform metrics/logs/errors | Observability |
| `system.maintenance.manage` | Maintenance mode, cache, cleanup | Observability |
| `system.backups.manage` | Manage backups/restore | Observability |
| `system.audit.view` | View platform audit log | Audit |

## 4. Rules

1. Every protected action maps to exactly one **required permission key** here
   (composite actions may require several). See `ACCESS_POLICIES.md`.
2. **No code branches on a role name.** Roles bundle these keys
   (`ROLE_BUILDER.md`).
3. **System keys** are visible only in the Platform Context; **workspace keys**
   are further gated by subscription + enabled modules for *visibility* (not
   authorization).
4. Adding a module adds keys here; it never changes the permission engine.
5. The catalog is **append-mostly**: renaming/removing a key is a breaking change
   requiring an ADR and a migration of role assignments.

---

### Related Documents
`PERMISSION_MODEL.md` · `SYSTEM_PERMISSIONS.md` · `WORKSPACE_PERMISSIONS.md` ·
`ROLE_BUILDER.md` · `ACCESS_POLICIES.md` · `SECURITY_MATRIX.md` ·
`AUDIT_EVENTS.md` · `PERMISSION_MATRIX.md`
