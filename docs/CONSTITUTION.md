# The Nizam Constitution

> **This document has higher priority than any implementation.** Every phase, every
> module, every plugin, every document, and every contributor MUST follow it.
> **Violating this Constitution is considered a bug.**

**Status:** Ratified (governing document) | **Version:** 1.0.0 | **Adopted:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

This is the supreme governing document of **Nizam — the Bayan AI Operating System**. It sits
above the Phase-1 "Project Constitution" summarized in the [README](../README.md) and above every
[ADR](./16-ADR.md); where any lower document conflicts with this one, this one wins. ADRs remain the
mechanism that records *how* these principles are realized; they may not contradict the principles below.

---

## 1. Project Mission

Build the **world's most extensible AI Operating System for businesses**. The platform lets
companies build an **AI Workforce without programming**. Everything must be understandable by
**non-technical users**.

Nizam is an **execution** operating system. It is **not** a chatbot, **not** a RAG product, and it
does **not** replace the Brain.

## 2. Target Users (in priority order)

Business Owners · Managers · HR · Marketing · Finance · Operations · Customer Support · Employees —
**people with zero programming knowledge.** **Never design for developers first. Developers are
secondary users.**

## 3. Core Principles

Everything is a **Plugin**. Everything is **Replaceable**. Everything is **Auditable**. Everything is
**Observable**. Everything is **Versioned**. Everything is **Documented**. Everything is **Tested**.
Everything is **Recoverable**. Everything is **Configurable**. Everything is **Upgradeable**.
**Nothing depends on a specific vendor.**

## 4. Architecture (non-negotiable)

- **Native PHP 8.4 only.** No Laravel. No Symfony. **No framework** inside the core.
- **PSR standards** (PSR-1, PSR-4, PSR-7, PSR-11, PSR-12, PSR-14, PSR-15, PSR-16; plus PSR-3 log and
  PSR-20 clock as interface packages). **Composer for dependency management only.**
- **DDD**, **Hexagonal Architecture**, **Clean Architecture**, **Event-Driven**, **CQRS where
  appropriate.** **Every dependency points inward** (domain depends on nothing; I/O lives in
  Infrastructure adapters behind ports).
- The platform *is* our framework: HTTP, Routing, Middleware, DI Container, Events, Queue, Scheduler,
  Database/ORM layer, Configuration, Service Container, and Module/Plugin Loader are **built by us**.
  Governed by [ADR-0015](./16-ADR.md#adr-0015).

## 5. The Brain

**Bayan is the Brain.** This platform **never replaces Bayan**. It orchestrates execution: it manages
AI Workers, and executes Tools, Automations, and Integrations. Bayan is reached only through the
Anti-Corruption Layer ([ADR-0006](./16-ADR.md#adr-0006)).

## 6. The AI Workforce (everything is a Plugin)

Departments · Managers · Team Leaders · Workers · Tools · Automations · Integrations · Knowledge
Sources · AI Providers are **all Plugins**. Everything is **installable** and **removable** without
modifying the Core ([ADR-0016](./16-ADR.md#adr-0016)).

## 7. Organization Governance

**The AI may recommend. The Owner decides. Nothing changes automatically unless explicitly enabled.**
Structural changes flow through an approval queue with preview, impact estimate, and rollback.

## 8. Non-Technical UX

The platform must be usable without technical knowledge. **Never expose implementation details.**
Every page explains itself ("What does this page do?"). Every input carries: a clear title, a **(!)
Help** popup, examples, how to obtain the value, a business explanation, and common mistakes.
Realized per [ADR-0012](./16-ADR.md#adr-0012) and [22-UIUX-Guidelines](./22-UIUX-Guidelines.md).

## 9. Security

Least privilege · encrypted secrets · tenant isolation · a complete audit trail · version history ·
rollback · **approval before dangerous actions.** No stack traces or internal exceptions are ever
shown to users — only friendly, business-language messages.

## 10. Quality (Definition of Done)

**No placeholders. No TODOs. No unfinished work.** Every file documented; every API documented; every
plugin documented; **every test passes**; every release audited. A component is "done" only when it
compiles, its tests are green, its folder has a `README.md`, and its documents are updated in the same
change.

## 11. The Final Rule — Decision Priorities

Whenever implementation choices exist, choose the solution that maximizes, in balance:
**Scalability, Maintainability, Replaceability, Performance, Developer Experience, Business-User
Experience, and Long-term sustainability.** **Never optimize for short-term convenience.**

---

## How this Constitution is enforced

1. **ADRs** ([16-ADR.md](./16-ADR.md)) record every architectural decision; none may violate §3–§9.
2. **Coding Standards** ([13-Coding-Standards.md](./13-Coding-Standards.md)) make §4 and §10 lint-enforceable.
3. **Per-phase audits** (`docs/audit/`, `PHASE_*_AUDIT.md`) verify compliance before a phase is accepted.
4. **CI gates**: `php -l`, PHPStan (max), PHP-CS-Fixer (PER/PSR-12), and PHPUnit must pass; every new
   folder must contain a `README.md`; documentation must be updated in the same change as behavior.

## Delivery Discipline (how a program this large stays Constitution-compliant)

The system is delivered as **verified, dependency-ordered increments** (the phase roadmap in
[15-Project-Roadmap.md](./15-Project-Roadmap.md)). Because §10 forbids unfinished/placeholder work,
each increment is only committed when it is **real, compiling, and test-green** — the Constitution
explicitly favors a smaller *fully-finished* slice over a larger *unfinished* one. Scope is sequenced;
quality is never traded away to fake breadth.

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Constitution ratified as the supreme governing document. |
