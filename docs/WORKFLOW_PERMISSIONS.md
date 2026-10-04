# WORKFLOW_PERMISSIONS — HaHireAI

Granular `workflow.*` permissions (the **Workflow** group in
`app/Modules/Permissions/Domain/PermissionCatalog.php`). HaHireAI ships **zero
default roles** — an owner grants these to roles explicitly (PERMISSION_MODEL.md).
The sidebar shows Workflows/Collections only when `workflow.view` is held **and**
the `automation` plan feature is enabled.

| Permission | Allows |
|---|---|
| `workflow.view` | View workflows, executions, version history, collections |
| `workflow.create` | Create/edit/save a workflow; use templates; run & toggle; manage collections & records |
| `workflow.update` | Edit a workflow |
| `workflow.delete` | Delete a workflow |
| `workflow.execute` | Manually run a workflow |
| `workflow.publish` | Publish / unpublish |
| `workflow.pause` | Pause / resume |
| `workflow.logs` | View execution logs |
| `workflow.templates` | Use & manage templates |
| `workflow.variables` | Manage variables & dynamic collections |
| `workflow.settings` | Manage workflow settings |

## Enforcement

Every controller method gates before acting: `workflow.view` for read pages
(index, templates gallery, versions, execution detail, collections) and
`workflow.create` for mutations (save, run, toggle, restore a version, create a
collection, add/delete records, use a template). The gate also redirects
unauthenticated users to `/login`, requires a resolved workspace, and verifies the
CSRF token on POST.

`certify` asserts each `workflow.*` key exists in the catalog and that the gating
contracts resolve. See `WORKFLOW_ENGINE.md`.
