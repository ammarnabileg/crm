# AI ENGINE — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`, `ARCHITECTURE.md`. **Deep spec:** Phase 11 docs.

---

## 1. Purpose & Scope

This is the **Phase-1 canonical overview** of the **AI Engine** — the central
intelligence layer of HaHireAI. It establishes the principles, the capability
catalog, the provider abstraction, per-workspace configuration, and the
guardrails that every module MUST honor. It is intentionally
implementation-light; the full technical specification (contracts, schemas,
queues, streaming protocol) is deferred to the **Phase 11** AI Engine documents.

The AI Engine is a single module in the **Intelligence** layer (`MODULES.md`).
Per the dependency map, it depends on `Core`, `Settings`, `Integration` (for
provider transport), and `Audit`; business modules depend on **it**, never the
reverse, and never on a provider.

**Interpretation keywords** (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119, per
`PROJECT_CONSTITUTION.md`.

---

## 2. Principle: AI Is an Engine, Not a Feature

The Constitution states it as a Golden Rule: **"AI is an engine, not a feature.
Every recruitment workflow MUST be able to route through the central AI Engine"**
(`PROJECT_CONSTITUTION.md` §3.2, §15.6). This document is the elaboration.

```
  ┌───────────────────────────────────────────────────────────────────┐
  │  Modules request CAPABILITIES ── never PROVIDERS                    │
  │                                                                     │
  │  Recruitment / Workflow / Reports ─┐                               │
  │                                     │  "Analyze CV"                 │
  │                                     │  "Run Interview"              │
  │                                     ▼  "Summarize Candidate"        │
  │                            ┌──────────────────┐                     │
  │                            │   AI ENGINE      │  (one contract)     │
  │                            └────────┬─────────┘                     │
  │                                     │ provider abstraction          │
  │            ┌────────┬────────┬──────┴───┬─────────┬────────┐        │
  │         OpenAI  Anthropic  Gemini   OpenRouter  Azure   Ollama      │
  └───────────────────────────────────────────────────────────────────┘
```

Binding consequences:

- A module **MUST** obtain AI behavior by requesting a **capability** from the AI
  Engine. A module **MUST NOT** call OpenAI, Anthropic, Google Gemini,
  OpenRouter, Azure OpenAI, Ollama, or any other provider/SDK directly.
- A module **MUST NOT** embed prompts, API keys, model names, or provider
  transport. Those are the AI Engine's concern (§3–§5).
- This indirection is what lets a workspace switch providers, or the platform add
  a new provider, **without touching a single business module** (§3,
  `ARCHITECTURE.md` §9).

---

## 3. Capability Catalog

A **capability** is a named, provider-agnostic unit of AI work that a module can
request. The Phase-1 catalog (Phase 11 may extend it; names are display-level and
MUST stay stable as request identifiers):

| Capability | What it does (advisory output) | Typical caller |
|---|---|---|
| **CV / Resume Analysis** | Extracts and assesses experience, skills, fit. | Applications |
| **Resume Parsing** | Structures a raw CV file into fields. | Applications |
| **AI Interview** | Conducts/scores a conversational interview. | Interviews |
| **Interview Questions** | Generates role-specific question sets. | Jobs / Templates |
| **Candidate Summary** | Concise summary of a candidate for reviewers. | Candidate Profiles |
| **Hiring Recommendation** | Suggests advance/hold/reject with rationale. | Pipeline / Interviews |
| **Candidate Comparison** | Ranks/contrasts shortlisted candidates. | Pipeline |
| **JD Generation** | Drafts a job description from inputs. | Jobs |
| **Email Generation** | Drafts candidate-facing communication. | Recruitment / Workflow |
| **Translation** | Translates content (e.g. AR ⇄ EN). | Any |
| **Skills Analysis** | Identifies and weighs skills. | Candidate Profiles |
| **Strengths / Weaknesses Analysis** | Surfaces evaluative signals. | Candidate Profiles |
| **Risk Analysis** | Flags potential hiring risks. | Candidate Profiles |
| **Culture-Fit Analysis** | Assesses alignment with workspace context. | Candidate Profiles |

Every capability in this catalog produces **advisory** output only (§7). New
capabilities are added to this catalog and exposed through the same contract —
adding one MUST NOT require changes in calling modules beyond opting in.

---

## 4. Provider Abstraction

The AI Engine presents **one contract** to the platform and hides a pluggable set
of providers behind it.

```
        Capability request
               │
               ▼
   ┌───────────────────────────┐
   │   AI Engine (Application)  │  selects provider+model from workspace config
   └─────────────┬─────────────┘
                 ▼
   ┌───────────────────────────┐
   │   Provider Contract        │  one interface, many adapters
   └─┬───┬───┬───┬───┬───┬──────┘
     │   │   │   │   │   └── Ollama (self-hosted / local)
     │   │   │   │   └────── Azure OpenAI
     │   │   │   └────────── OpenRouter (gateway)
     │   │   └────────────── Google Gemini
     │   └────────────────── Anthropic
     └────────────────────── OpenAI
```

- **Supported providers (Phase 1 intent):** OpenAI, Anthropic, Google Gemini,
  OpenRouter, Azure OpenAI, Ollama.
- **Adding a provider** means implementing the provider contract with a new
  adapter and registering it — **no business module changes** (`ARCHITECTURE.md`
  §9; `MODULES.md` §5: AI is *requested as a capability*).
- Provider transport (HTTP, auth, retries) is reached via the **Integration**
  module per the dependency map; the AI Engine owns selection, prompting,
  fallback, and accounting.
- Modules remain entirely unaware of *which* provider served a request.

---

## 5. Per-Workspace AI Configuration

AI configuration is **per workspace** and never inherited from another workspace
(`WORKSPACE_MODEL.md` §3–§4: settings are independent per tenant). System
defaults apply only when a workspace setting is absent.

Each workspace MAY configure:

| Setting | Purpose |
|---|---|
| **Provider** | Which provider serves this workspace. |
| **Model** | The specific model for a provider. |
| **Temperature** | Output determinism/creativity. |
| **Max tokens** | Upper bound on response size. |
| **Streaming** | Whether responses stream incrementally (§6). |
| **Prompt version** | Which versioned prompt template to use (§5.1). |
| **Language** | Default output language (e.g. AR/EN). |
| **Cost limits** | Spend caps (per period / per capability). |
| **Fallback rules** | What to do on failure/limit (§6). |

### 5.1 API Keys — own vs platform

- A workspace MAY use **its own provider API keys** ("bring your own key") **or**
  the **platform's keys**, where the plan allows (`Licensing`/`Subscriptions`,
  `MODULES.md`).
- All API keys **MUST** be **encrypted at rest** and **MUST NOT** be displayed
  again after they are saved (write-only from the UI's perspective; only a masked
  hint may be shown). This aligns with secret-handling in
  `PROJECT_CONSTITUTION.md` §10 and `SECURITY_GUIDE.md`.
- Keys are **workspace-scoped**: one workspace's keys, models, and configuration
  are invisible to every other workspace (§8 tenant isolation).

System-level AI administration (global provider definitions, platform keys) is
gated by the `system.ai.manage` permission and lives in the Platform Context
(`PERMISSION_MODEL.md` §6).

---

## 6. Prompt Engine

Prompts are **first-class, versioned assets** — they are **never hard-coded** in
services (mirrors "no hard-coded roles" / "system over pages" from the
Constitution).

The Prompt Engine provides:

- **Templates** — named, reusable prompt definitions per capability.
- **Versioning** — every template is versioned; a workspace pins a
  `prompt version` (§5) so behavior is reproducible and auditable. New versions
  roll out deliberately.
- **Variables** — typed placeholders bound at request time (e.g. job, candidate,
  CV text) so prompts are data-driven, not string-concatenated in code.
- **Localization** — templates resolve to the configured `language` (AR/EN and
  beyond), consistent with the bilingual product requirement.

```
  Capability request + variables
        │
        ▼
  Prompt Engine: resolve template@version, inject variables, localize
        │
        ▼
  Composed prompt ──▶ selected provider/model
```

A service requesting a capability supplies **variables and context**, not prompt
text. This keeps prompts reviewable, versioned, and changeable without code
deployment.

---

## 7. Conversation, Streaming, Fallback, Usage & Cost (overview)

Each bounded interaction with the engine is an **`AI Session`**
(`DOMAIN_MODEL.md` §3) — prompts, responses, usage, and cost belong to it.

- **Conversation:** multi-turn capabilities (e.g. AI Interview) maintain a
  bounded conversation within one `AI Session`, workspace-scoped.
- **Streaming:** when a workspace enables streaming, responses MAY be delivered
  incrementally; otherwise they return whole. Long/heavy AI work runs
  **asynchronously via the queue** so web requests stay responsive
  (`PROJECT_CONSTITUTION.md` §11, `ARCHITECTURE.md` §8).
- **Fallback:** on provider error, timeout, or a tripped limit, the engine
  applies the workspace's **fallback rules** (e.g. retry, switch to a fallback
  provider/model, or fail gracefully). Fallback is the engine's responsibility —
  callers receive a result or a clean, typed failure, never a raw provider error.
- **Usage & cost tracking:** every session records token usage and computed cost,
  enforced against the workspace's **cost limits** (§5). Usage is attributed to
  the originating workspace for billing/observability (`Reports`, `Billing`,
  `Observability`).

```
  ┌──────────── AI Session (workspace-scoped) ────────────┐
  │  prompt(s) → response(s)   │   tokens → cost           │
  │  conversation state        │   limit checks            │
  │  streaming (optional)      │   fallback applied?        │
  └──────────────────┬─────────────────────┬──────────────┘
                     │ records              │ enforces
                     ▼                      ▼
                 Audit / Reports        Cost limits
```

---

## 8. Human-in-the-Loop, Guardrails, Auditability & Isolation

### 8.1 Human-in-the-loop (advisory by law)

**All AI output is advisory.** AI never takes an irreversible business action on
its own. A human with the appropriate permission can **override any AI result**;
the human decision governs. This is the binding rule echoed in
`APPLICATION_FLOW.md` §7 (evaluations are recommendations; human override always
wins). Actions that *consume* AI output (e.g. running an AI interview) are still
permission-gated — e.g. `interview.ai.run` (`PERMISSION_MODEL.md` §2, §6).

### 8.2 Guardrails

- Capability requests are **validated** (inputs, size, language) before dispatch.
- **Cost and rate limits** are enforced per workspace (§5, §7).
- Outputs are treated as untrusted content: they are escaped on render and never
  executed (`PROJECT_CONSTITUTION.md` §10 — "all output is escaped on render").
- The engine MUST degrade gracefully when AI is unavailable, so dependent
  workflows are not hard-blocked (`MODULES.md` §1 graceful degradation).

### 8.3 Auditability

Significant AI activity is **auditable** via the shared `Audit` service —
who/what/when/which workspace, which capability, which provider/model, and cost
(`PROJECT_CONSTITUTION.md` §3.9, `MODULES.md`). The system observes truth rather
than relying on self-report.

### 8.4 Tenant isolation of AI usage

- Every `AI Session`, configuration value, key, prompt binding, conversation, and
  usage/cost record is **workspace-scoped** and carries `workspace_id`.
- One workspace's AI configuration, keys, prompts, sessions, and usage are
  **never** visible to another workspace. Cross-workspace leakage of AI data is a
  **critical security defect** (`WORKSPACE_MODEL.md` §3,
  `PROJECT_CONSTITUTION.md` §10).

---

## 9. How Modules Invoke the AI Engine

Modules depend on the AI Engine's **published contract** (resolved from the
container) and request a **capability** with **context/variables** — never a
provider, model, prompt, or key.

```text
  // ILLUSTRATIVE pseudo-signature — NOT a binding API (see Phase 11 spec).
  // The real contract, types, and async semantics are defined in Phase 11.

  result = aiEngine.request(
      capability : "ANALYZE_CV",          // from the catalog (§3)
      workspaceId: <current workspace>,   // tenant scope (mandatory)
      input      : { cvFileRef, jobRef }, // variables for the Prompt Engine (§6)
      options    : { /* engine resolves provider/model/prompt from config */ }
  )
  // result is ADVISORY (§8.1): a recommendation/structured output or a typed failure.
```

What the caller does **not** do, and the engine does instead:

- choose the provider/model (from workspace config, §5),
- compose and localize the prompt (Prompt Engine, §6),
- handle streaming/conversation, fallback, retries (§7),
- record usage, cost, and audit (§7, §8.3),
- enforce limits and tenant isolation (§5, §8.4).

This contract-only, capability-first invocation is what keeps AI **central
infrastructure** rather than scattered feature code, and is the rule the rest of
the platform is built to obey.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` · `MODULES.md` · `DOMAIN_MODEL.md` ·
`WORKSPACE_MODEL.md` · `PERMISSION_MODEL.md` · `APPLICATION_FLOW.md` ·
`SECURITY_GUIDE.md` · Phase 11 AI Engine specification
