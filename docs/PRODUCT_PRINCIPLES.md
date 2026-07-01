# Nizam — Product Principles

> A plain-language restatement of the product and user-experience principles that govern
> **Nizam — the Bayan AI Operating System**, written for **non-technical stakeholders**
> (owners, managers, HR, finance, operations, support). It is a companion, not a competitor,
> to the [Constitution](./CONSTITUTION.md): where anything here is unclear or seems to conflict,
> **the [Constitution](./CONSTITUTION.md) is the authoritative source and wins.**

**Status:** Approved (companion to the Constitution) | **Version:** 1.0.0 | **Last updated:** 2026-07-01 | **Owner:** Architecture (Nizam Core)

---

## 1. What We Are Building (Mission)

Nizam is the **world's most extensible AI Operating System for businesses**. Its purpose is to let
a company build an **AI Workforce without writing a single line of code**. Everything a business
person sees must be **understandable by someone with zero programming knowledge**.

Nizam is an **execution** system — it *does work*. It is **not** a chatbot, **not** a
question-answering/RAG product, and it does **not** replace the business's brain, **Bayan**. Bayan
decides *what* should happen; Nizam makes it *actually* happen — safely, and with a record of what it did.

## 2. Who We Design For

We design, in priority order, for **Business Owners, Managers, HR, Marketing, Finance, Operations,
Customer Support, and Employees** — **people who do not program.** Developers matter, but they are
**secondary** users. If a choice helps a developer but confuses a business owner, we choose the
business owner. We **never design for developers first.**

## 3. Everything Is a Building Block

Every capability in Nizam is a **Plugin** — an installable, removable building block:

- **Replaceable.** Any part can be swapped for another without rebuilding the system.
- **Auditable.** Every action leaves a clear, human-readable trail of who did what, when, and why.
- **Observable.** You can always see what the system is doing; nothing happens invisibly.
- **Versioned.** Every change has a version and a history you can review and roll back.
- **Documented, Tested, Recoverable, Configurable, Upgradeable.** Each block explains itself, is
  proven to work, can be restored, adjusted, and upgraded on its own.
- **No lock-in.** Nothing depends on a single vendor.

Departments, Managers, Team Leaders, Workers, Tools, Automations, Integrations, Knowledge Sources,
and AI Providers are **all plugins** — added or removed without touching the core of the product.

## 4. AI Recommends, the Owner Decides

This is the promise that keeps you in control:

> **The AI may recommend. The Owner decides. Nothing changes automatically unless you explicitly turn it on.**

Any change to how your organization is structured or how it works flows through an **approval queue**
that shows you, before you agree: a **preview** of the change, an **estimate of its impact**, and a
guaranteed way to **roll it back**. The system observes and suggests; it never rearranges your
business behind your back.

## 5. Built for Non-Technical People (the UX Rules)

The product must be usable **without any technical knowledge**:

- **No jargon, no internals.** We never show implementation details, error codes, or stack traces —
  only clear, business-language messages.
- **Every page explains itself.** Each screen answers "What does this page do?" in plain words.
- **Every input carries a (!) Help popup.** Beside each field, a **(!) Help** icon opens a popup that
  gives: a clear **title**, **what** the field is and **why** it matters, an **example**, **how to
  obtain the value**, a plain **business explanation**, and the **common mistakes** to avoid.
- **Guided, not overwhelming.** Complex setup is delivered through step-by-step **Wizards** with a
  simple **Basic mode** by default and an optional **Advanced mode** for power users.
- **Bilingual.** The experience works fully in **Arabic and English**, right-to-left and left-to-right.

## 6. Safe by Default (Security in Plain Terms)

Your data and your business are protected as a default, not as an extra:

- **Least privilege.** Every part gets only the access it truly needs.
- **Encrypted secrets** and **tenant isolation** — one company's data is never visible to another.
- **Complete audit trail, version history, and rollback** for everything that matters.
- **Approval before anything dangerous.** High-consequence actions stop and ask first.
- **Friendly messages only.** Users never see technical errors — just clear, helpful language.

## 7. What "Done" Means

A capability is only considered finished when it **actually works, is proven by passing tests, is
documented, and its documentation is updated in the same change**. There are **no placeholders, no
"to-do later," and no half-finished features** shipped to you. We would rather deliver a smaller
piece that is **completely finished** than a larger piece that is unfinished.

---

## Where These Principles Come From

Every principle above is a plain-language restatement of the **[Constitution](./CONSTITUTION.md)** —
the supreme governing document of Nizam. The mapping is direct:

| Principle here | Constitution section |
|----------------|----------------------|
| Mission (§1) | [§1 Project Mission](./CONSTITUTION.md#1-project-mission) |
| Who we design for (§2) | [§2 Target Users](./CONSTITUTION.md#2-target-users-in-priority-order) |
| Everything is a building block (§3) | [§3 Core Principles](./CONSTITUTION.md#3-core-principles), [§6 The AI Workforce](./CONSTITUTION.md#6-the-ai-workforce-everything-is-a-plugin) |
| AI recommends, owner decides (§4) | [§7 Organization Governance](./CONSTITUTION.md#7-organization-governance) |
| Non-technical UX & (!) Help (§5) | [§8 Non-Technical UX](./CONSTITUTION.md#8-non-technical-ux) |
| Safe by default (§6) | [§9 Security](./CONSTITUTION.md#9-security) |
| What "done" means (§7) | [§10 Quality (Definition of Done)](./CONSTITUTION.md#10-quality-definition-of-done) |

## Related Documents

- [CONSTITUTION.md](./CONSTITUTION.md) — the supreme, authoritative governing document.
- [15-Project-Roadmap.md](./15-Project-Roadmap.md) — the phased delivery plan.
- [22-UIUX-Guidelines.md](./22-UIUX-Guidelines.md) — the detailed UX rules realizing §5.
- [16-ADR.md](./16-ADR.md) — the architecture decisions behind these principles.

## Change Log

| Version | Date | Author | Change |
|---------|------|--------|--------|
| 1.0.0 | 2026-07-01 | Architecture (Nizam Core) | Initial product-principles restatement of the Constitution for non-technical stakeholders. |
