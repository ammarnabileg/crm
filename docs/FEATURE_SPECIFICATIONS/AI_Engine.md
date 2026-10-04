# FEATURE SPEC — AI Engine

> **Status:** Draft (Phase 2) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Module:** AI Engine · **Layer:** Intelligence · **Implemented in:** Phase 11
> **Defers to:** `MODULES.md`, `ARCHITECTURE.md`.

## 1. Purpose

The **AI Engine** is HaHireAI's **central, multi-provider AI capability layer** —
the single intelligence service every workflow can route through
(`PROJECT_CONSTITUTION.md` §3.2, §15.6; `AI_ENGINE.md`). It exposes
**provider-agnostic capabilities** (e.g. "CV / Resume Analysis", "AI Interview",
"Hiring Recommendation") behind **one contract**, and hides provider selection,
prompt composition, conversation/streaming, fallback, and usage/cost accounting
from every caller.

The binding principle is **"AI is an engine, not a feature."** Modules request a
**capability**; they MUST NOT call OpenAI, Anthropic, Google Gemini, OpenRouter,
Azure OpenAI, Ollama, or any other provider/SDK, and MUST NOT embed prompts, API
keys, model names, or provider transport (`AI_ENGINE.md` §2). This indirection is
what lets a workspace switch providers — or the platform add a provider — without
touching a single business module.

A second binding principle is **human-in-the-loop**: **all AI output is
advisory**. The engine never takes an irreversible business action on its own; a
permitted human can override any AI result (`AI_ENGINE.md` §8.1).

## 2. Scope

### In scope

- A **capability catalog** (`AI_ENGINE.md` §3) of named, provider-agnostic units
  of AI work, requested via one published contract.
- A **provider abstraction** with pluggable adapters: OpenAI, Anthropic, Google
  Gemini, OpenRouter, Azure OpenAI, Ollama (`AI_ENGINE.md` §4).
- **Per-workspace AI configuration:** provider, model, temperature, max tokens,
  streaming, prompt version, language, cost limits, fallback rules.
- **API key management:** "bring your own key" or platform keys; keys
  **encrypted at rest** and **write-only** from the UI's perspective.
- A **Prompt Engine:** versioned, localized, variable-driven prompt templates
  (prompts are first-class assets, never hard-coded).
- **AI Sessions:** bounded interactions carrying prompts, responses, conversation
  state, usage, and cost.
- **Conversation, streaming, and fallback** orchestration.
- **Usage and cost tracking**, enforced against workspace cost limits and
  attributed per workspace.
- **Human override** support so any consumed result remains advisory.
- **Auditability** of significant AI activity via the shared `Audit` service.
- System-level AI administration surface (global provider definitions, platform
  keys) gated by `system.ai.manage` in the Platform Context.

### Out of scope

- **Business semantics** of any capability output (e.g. what a hiring
  recommendation *means* for an application) — owned by **Recruitment**.
- **Provider transport** (HTTP, auth, retries at the wire level) — reached via the
  **Integration** module per the dependency map (`AI_ENGINE.md` §4).
- **Automation/orchestration of multi-step processes** — owned by the **Workflow
  Engine**, which invokes the AI Engine for AI steps.
- **Plan-based entitlement** to AI features and platform-key access — owned by
  **Licensing/Subscriptions**; the engine enforces the resulting limits.
- **Billing of AI cost** — the engine records cost; **Billing** charges for it.

## 3. Inputs

- **Capability request:** a capability identifier from the catalog (e.g.
  `ANALYZE_CV`, `RUN_INTERVIEW`), the mandatory `workspace_id` (tenant scope),
  and **input variables/context** for the Prompt Engine (e.g. CV file reference,
  job reference, candidate reference) — **never** prompt text, provider, model, or
  key (`AI_ENGINE.md` §9).
- **Conversation turns:** for multi-turn capabilities (e.g. AI Interview),
  subsequent messages bound to an existing `AI Session`.
- **Workspace AI configuration:** provider, model, temperature, max tokens,
  streaming flag, pinned prompt version, language, cost limits, fallback rules.
- **API keys:** workspace-owned keys or platform keys (resolved internally; never
  echoed back).
- **System administration input:** global provider definitions, platform keys,
  and capability/prompt catalog management (System Owner, `system.ai.manage`).
- **Shared-service inputs:** authenticated `User` + active `Workspace`; permission
  decisions; provider transport via Integration; audit sink.

## 4. Outputs

- **Capability result** — a structured, **advisory** output or a **clean, typed
  failure** (callers never receive a raw provider error; `AI_ENGINE.md` §7).
- **Streamed partial responses** when the workspace enables streaming.
- **`AI Session` records** — prompts, responses, conversation state, token usage,
  computed cost, provider/model used, fallback applied.
- **AI domain events** (§7) for downstream consumers.
- **Usage/cost signals** attributed to the originating workspace for
  **Reports/Analytics**, **Billing**, and **Observability**.
- **Audit entries** for significant AI activity (who/what/when/which workspace,
  capability, provider/model, cost).
- **Masked key hints** for the configuration UI (never the secret itself).

## 5. Dependencies (modules + contracts consumed; shared services used)

| Dependency | Type | Why |
|---|---|---|
| **Core Kernel** | Foundation | Container, events, config, logging, queue dispatch. |
| **Settings** | Contract | Per-workspace AI configuration and system defaults (`AI_ENGINE.md` §5). |
| **Integration** | Contract (capability) | Provider transport (HTTP/auth/retries) to external providers (`AI_ENGINE.md` §4). |
| **Audit** | Shared service | Records significant AI activity (`AI_ENGINE.md` §8.3). |
| **Permissions** | Contract | Authorizes `ai.configure`, `ai.run`, `ai.keys.manage`, `system.ai.manage`. |
| **Workspaces** | Contract | Tenant scope for every session, config, key, and usage record. |
| **Licensing / Subscriptions** | Contract (optional) | Entitlement to AI features and platform-key access; the engine enforces resulting limits. |

The AI Engine depends only on `Core`, `Settings`, `Integration`, and `Audit` per
`MODULES.md` §5; **business modules depend on it, never the reverse, and never on
a provider** (`AI_ENGINE.md` §1).

## 6. Permissions (keys this module declares)

Grammar `resource.action` (lowercase, dot-separated; `PERMISSION_MODEL.md` §2).
Workspace permissions unless prefixed `system.`.

- `ai.run` — request/execute an AI **capability** for the current workspace
  (capabilities that *consume* AI are still individually gated by their owning
  module, e.g. `interview.ai.run` in Recruitment).
- `ai.configure` — view and change the workspace's AI configuration (provider,
  model, temperature, max tokens, streaming, prompt version, language, cost
  limits, fallback rules).
- `ai.keys.manage` — create, rotate, and remove the workspace's provider API keys
  (write-only; keys are never displayed after save).
- `system.ai.manage` *(system)* — manage global provider definitions, platform
  keys, and the capability/prompt catalog in the Platform Context
  (`PERMISSION_MODEL.md` §6; `AI_ENGINE.md` §5.1).

Deny-by-default applies to every key. The human-override action that supersedes an
AI result is authorized by the **consuming** module's permission (e.g.
`application.move`, `interview.evaluate`), keeping AI advisory by law.

## 7. Events (Published / Subscribed)

Grammar `<module>.<entity>.<event>`, past tense (`PROJECT_CONSTITUTION.md` §7).

### Published

- `ai.session.started` — a capability request began an `AI Session`.
- `ai.session.completed` — a session produced an advisory result.
- `ai.session.failed` — a session failed after fallback; a typed failure was
  returned to the caller.
- `ai.session.streamed` — (optional) incremental output was delivered.
- `ai.usage.recorded` — token usage and computed cost were recorded for a
  workspace.
- `ai.cost_limit.reached` — a workspace tripped a configured spend cap.
- `ai.fallback.applied` — the engine switched provider/model per fallback rules.
- `ai.config.updated` — a workspace changed its AI configuration.
- `ai.key.rotated` — a workspace key was created/rotated/removed.

### Subscribed

The AI Engine is primarily a **service** invoked via its contract. It MAY
subscribe to:

- `subscriptions.subscription.changed` / `licensing.entitlement.changed` — to
  refresh a workspace's AI entitlements and platform-key eligibility.
- `workspaces.workspace.archived` / `workspaces.workspace.deleted` — to suspend
  AI activity and protect keys for an inactive tenant.

It is **invoked synchronously via its contract** by the Workflow Engine for AI
steps (not via events) and by Recruitment for capabilities.

## 8. Data Owned (conceptual entities only — defer to `DATABASE_ARCHITECTURE.md`)

All workspace-scoped entities carry `workspace_id` and a ULID `id` (`CHAR(26)`)
and are isolated per tenant. Global catalogs are explicitly noted.

- **AI Provider** *(global catalog)* — a supported provider definition
  (`AI_ENGINE.md` §4).
- **AI Model** *(global catalog; per-workspace selection)* — a model offered by a
  provider.
- **Workspace AI Configuration** *(workspace-scoped)* — provider, model,
  temperature, max tokens, streaming, pinned prompt version, language, cost
  limits, fallback rules.
- **AI Key** *(workspace-scoped; encrypted at rest, write-only)* — a workspace's
  provider API key, or a reference to platform keys.
- **Prompt Template** *(catalog; versioned)* — a named, reusable prompt
  definition per capability, with typed variables and localization
  (`AI_ENGINE.md` §6).
- **Prompt Version** — an immutable, pinnable version of a template.
- **AI Session** *(workspace-scoped)* — one bounded interaction: prompts,
  responses, conversation state, provider/model used, fallback applied.
- **AI Message / Turn** — an individual prompt or response within a session.
- **AI Usage Record** *(workspace-scoped)* — token usage and computed cost per
  session, attributed to the workspace.
- **Cost Limit** *(workspace-scoped)* — configured spend caps (per period / per
  capability) and current consumption.

All AI keys MUST be **encrypted at rest** and MUST NOT be displayed again after
saving (a masked hint only). Every session, configuration value, key, prompt
binding, conversation, and usage/cost record is workspace-scoped; cross-workspace
leakage of AI data is a **critical security defect** (`AI_ENGINE.md` §8.4).

## 9. Acceptance Criteria (testable checklist)

- [ ] A module obtains AI behavior **only** by requesting a **capability** from
      the published contract; no business module calls a provider/SDK directly
      (verified by static analysis / dependency check).
- [ ] A capability request supplies **variables and context only** — never prompt
      text, provider, model, or key (`AI_ENGINE.md` §9).
- [ ] The engine selects provider + model from **workspace configuration**, with
      system defaults applied only when a workspace setting is absent.
- [ ] A workspace MAY use **its own API keys or platform keys** where the plan
      allows; keys are **encrypted at rest** and **never redisplayed** after save
      (only a masked hint).
- [ ] One workspace's configuration, keys, prompts, sessions, and usage are
      **never** visible to another workspace (tenant-isolation test across two
      workspaces).
- [ ] Prompts are **versioned templates** with typed variables and localization;
      a workspace can **pin a prompt version** for reproducibility; no prompt text
      is hard-coded in services.
- [ ] Each interaction is recorded as an **`AI Session`** capturing prompts,
      responses, provider/model, usage, and cost.
- [ ] **Streaming** is delivered incrementally only when the workspace enables it;
      otherwise responses return whole.
- [ ] Long/heavy AI work runs **asynchronously via the queue**; web requests stay
      within the performance budget (`PROJECT_CONSTITUTION.md` §11).
- [ ] On provider error, timeout, or a tripped limit, the engine applies the
      workspace's **fallback rules** and returns a **result or a clean typed
      failure** — never a raw provider error.
- [ ] **Usage and cost** are recorded per session and **enforced** against
      workspace cost limits; exceeding a cap emits `ai.cost_limit.reached` and
      degrades gracefully.
- [ ] **All AI output is advisory**; a permitted human can override any result via
      the consuming module, and the override governs (`AI_ENGINE.md` §8.1).
- [ ] Significant AI activity is **audited** (who/what/when/workspace/capability/
      provider/model/cost) via the shared `Audit` service.
- [ ] Outputs are treated as **untrusted content** — escaped on render, never
      executed (`AI_ENGINE.md` §8.2).
- [ ] **Adding a new provider** requires only a new adapter + registration, with
      **no business module changes**; adding a **capability** requires no caller
      change beyond opting in.
- [ ] The engine **degrades gracefully** when AI is unavailable, so dependent
      workflows are not hard-blocked.
- [ ] Every workspace-scoped record carries `workspace_id` and a ULID `id`; all
      access is tenant-guarded at the Infrastructure layer.
- [ ] System-level provider/key administration is reachable **only** with
      `system.ai.manage` in the Platform Context.

### Related Documents

`MODULES.md` · `ARCHITECTURE.md` · `AI_ENGINE.md` · `DOMAIN_MODEL.md` ·
`PERMISSION_MODEL.md` · `WORKSPACE_MODEL.md` · `APPLICATION_FLOW.md` ·
`SECURITY_GUIDE.md` · `DATABASE_ARCHITECTURE.md` ·
`FEATURE_SPECIFICATIONS/Recruitment.md` · `FEATURE_SPECIFICATIONS/Workflow_Engine.md`
