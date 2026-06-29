# FEATURE SPECIFICATIONS — HaHireAI

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `../MODULES.md`, `../SYSTEM_BLUEPRINT.md`, `../ARCHITECTURE.md`.

One specification per module. Each follows the same 9-section template
(Purpose · Scope · Inputs · Outputs · Dependencies · Permissions · Events ·
Data Owned · Acceptance Criteria). A module is **not implemented** before its
spec here is approved (Constitution §12, docs-first).

## Index (by layer)

### Foundation
| Spec | Responsibility | Phase |
|---|---|---|
| [Core_Kernel](./Core_Kernel.md) | Boot, container, router, config, env, logger, errors, events, registry, health | 7 |
| [Database](./Database.md) | Connection, schema builder, migration engine, transactions, base repository (no ORM) | 8 |
| [Installer](./Installer.md) | Zero-touch browser install + first System Owner | 8 |

### Identity & Access
| Spec | Responsibility | Phase |
|---|---|---|
| [Authentication](./Authentication.md) | Register/login/logout/reset, sessions, MFA-ready | 8 |
| [Users](./Users.md) | The single `User` identity & profile | 8 |
| [Permissions](./Permissions.md) | Catalog, roles (data), assignment, authorization | 8 |
| [Workspaces](./Workspaces.md) | Tenant lifecycle, settings, branding | 9 |
| [Memberships](./Memberships.md) | User↔Workspace links, invitations | 9 |

### Platform Services (shared)
| Spec | Responsibility | Phase |
|---|---|---|
| [Settings](./Settings.md) | Workspace & system settings registry | 9 |
| [Files](./Files.md) | Upload, ownership, visibility, retention | 9 |
| [Notifications](./Notifications.md) | Unified notification center | 9 |
| [Search](./Search.md) | Unified workspace-scoped search | 9 |
| [Audit](./Audit.md) | Immutable activity/audit trail | 9 |

### Business Domain
| Spec | Responsibility | Phase |
|---|---|---|
| [Recruitment](./Recruitment.md) | The hiring OS (Jobs, Applications, Candidates, Pipeline, Interviews, Offers, Talent Pool, Templates) | 10 |

### Intelligence
| Spec | Responsibility | Phase |
|---|---|---|
| [AI_Engine](./AI_Engine.md) | Central multi-provider AI capability layer | 11 |
| [Reports_Analytics](./Reports_Analytics.md) | Cross-module metrics, dashboards, exports | 10–15 |

### Process
| Spec | Responsibility | Phase |
|---|---|---|
| [Workflow_Engine](./Workflow_Engine.md) | Central automation, approvals, scheduler, jobs | 12 |

### Integration
| Spec | Responsibility | Phase |
|---|---|---|
| [Integration_Platform](./Integration_Platform.md) | API Gateway, webhooks, connectors, OAuth/SSO | 13 |

### Commerce
| Spec | Responsibility | Phase |
|---|---|---|
| [Subscriptions](./Subscriptions.md) | Per-workspace subscription lifecycle | 14 |
| [Billing](./Billing.md) | Payments, invoices, coupons | 14 |
| [Licensing](./Licensing.md) | Feature flags, tenant limits, entitlements | 14 |

### Operations
| Spec | Responsibility | Phase |
|---|---|---|
| [Observability](./Observability.md) | Health, metrics, logs, monitors, backups | 15 |

### Administration
| Spec | Responsibility | Phase |
|---|---|---|
| [System_Administration](./System_Administration.md) | Platform Context console (System Owners) | 8–15 |

**23 module specifications.** See `../MODULES.md` for the canonical module map and
dependency graph.
