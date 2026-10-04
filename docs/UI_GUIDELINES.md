# UI GUIDELINES — HaHireAI

> **Status:** Draft (Phase 1) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_CONSTITUTION.md`. **Deep UX:** Phase 5 docs.

---

## 0. About This Document

This document states the **Phase-1 UI principles** of HaHireAI — the binding rules
the user interface MUST obey from the first line of markup. It is deliberately
about *principles and constraints*, not pixels. The full UX system — screen
catalog, navigation/sidebar model, component library, and detailed flows — is
expanded in **Phase 5** (`SCREEN_CATALOG.md`, `NAVIGATION_ARCHITECTURE.md`,
`SIDEBAR_MODEL.md`).

This document **defers** to `PROJECT_CONSTITUTION.md` (the supreme reference). The
mandatory stack — **Native PHP 8.3+, MySQL 8+, TailwindCSS, Alpine.js (UI only,
when needed), Vanilla JavaScript, Composer (dependencies only)** — is fixed by
Constitution §5 and is not re-litigated here. Interpretation keywords (**MUST**,
**MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119, exactly as in
the Constitution.

> Code snippets in this document are **illustrative examples only**. They are not
> the implementation and MUST NOT be copied as canonical markup. No HTML/CSS/JS
> is delivered in Phase 1.

---

## 1. Core Principle — One Coherent Product, Not a Set of Pages

HaHireAI is **one coherent product**, engineered to the standard of Slack, Notion,
Linear, and the Stripe Dashboard — **system over screens** (Constitution §3.1).

- The UI is a **projection of the system**, never its definition. We design
  business domains; the interface renders them. A screen is a *view onto* the
  domain, not the place where the domain is invented.
- Every module's interface MUST feel like part of the **same product**: shared
  layout, shared components, shared tokens, shared interaction grammar. A user
  MUST NOT be able to tell where one module ends and another begins by a change
  in visual language.
- Consistency is a **requirement, not a preference**. The same action (create,
  edit, archive, filter, paginate, confirm) MUST look and behave the same
  everywhere. Divergent one-off UI is a defect.
- The UI **never** becomes the source of truth for authorization, tenancy, or
  state. Hiding a control is a courtesy to the user; the server still enforces
  every rule (see §2, §8, and `PERMISSION_MODEL.md` §5).

## 2. Dynamic Navigation — One Sidebar, Generated

There is exactly **ONE** sidebar in HaHireAI, and it is **generated dynamically**.
There are **NO role-based sidebars** (`WORKSPACE_MODEL.md` §7).

The navigation engine composes the sidebar from **four inputs** plus the active
context:

```
Sidebar = f(
  current User,            // who is signed in (one identity)
  current Workspace,       // the active tenant (or Platform Context)
  Permissions,             // effective permission keys here (deny by default)
  Subscription,            // plan status & entitlements for this workspace
  Enabled Modules          // modules turned on for this workspace
)
```

Binding rules:

- A navigation item MUST appear **only** when the current user holds the required
  **permission key** for it in the active context. Visibility is derived from the
  **same** permission checks that guard the server action — never from a role name
  (`PERMISSION_MODEL.md` §1, §5).
- A module's navigation MUST be hidden when the module is **not an Enabled Module**
  for the workspace, or when the **Subscription** does not entitle it
  (`DOMAIN_MODEL.md` §3, `WORKSPACE_MODEL.md` §8.4).
- The sidebar MUST be **data-driven**: modules contribute their navigation entries
  declaratively (via their manifest/registration), and the engine assembles them.
  Adding a module MUST NOT require editing a hard-coded menu (`ARCHITECTURE.md`
  §9, `MODULES.md` §7).
- The UI **MUST NOT** branch on a role's name anywhere — not in templates, not in
  view-models, not in JS (`PERMISSION_MODEL.md` Invariant 1).
- Hiding a sidebar item is **never** a substitute for server-side enforcement
  (`PERMISSION_MODEL.md` §5).

## 3. Operating Contexts — Platform vs Workspace

HaHireAI presents two operating contexts; the same sidebar engine serves both,
producing a different result from the same function (`SYSTEM_OVERVIEW.md` §6).

| | **Platform Context** | **Workspace Context** |
|---|---|---|
| **Who operates here** | System Owners (`system.*` permissions) | Workspace members, via roles/grants |
| **Scope** | The platform itself | One active workspace at a time |
| **Navigation source** | System permissions + platform modules | Workspace permissions + subscription + enabled modules |
| **Data visibility** | Platform-level + system audit | Strictly that workspace's data (tenant-isolated) |

- The UI MUST make the **active context unmistakable** — the user always knows
  whether they are operating the platform or working inside a specific workspace.
- A **workspace switcher** MUST let a user move between their workspaces, and (if
  granted `system.*`) into the Platform Context, all with **one login**
  (`USER_MODEL.md` §4, `WORKSPACE_MODEL.md` §7).
- Switching context MUST re-derive the entire sidebar and the visible UI from the
  new context's inputs. UI from one context MUST NOT leak into another.

## 4. Technology Usage Rules

The stack is fixed by Constitution §5. The UI layer applies it as follows:

- **Server-rendered first.** Views are rendered on the server (Presentation layer,
  `ARCHITECTURE.md` §2). The baseline experience MUST work as server-rendered HTML;
  client interactivity is **progressive enhancement**, not a prerequisite.
- **TailwindCSS for styling.** Styling MUST be **utility-first** via Tailwind, on
  top of the shared **design tokens** (§5). Bespoke, untokenized CSS SHOULD be
  avoided; when unavoidable it MUST be justified and consistent with the tokens.
- **Alpine.js ONLY for light UI interactivity, when needed.** Alpine is reserved
  for small, local enhancements (dropdowns, toggles, tabs, popovers, modals,
  inline validation hints). It MUST NOT carry business logic, own authoritative
  state, or grow into an application framework.
- **Vanilla JavaScript otherwise.** Anything beyond Alpine's light remit is plain,
  modular Vanilla JS. Keep it minimal and scoped (§10).
- **No heavy SPA framework.** React, Vue, Angular, Svelte, and similar are
  **forbidden** as the UI foundation (Constitution §5; `SYSTEM_OVERVIEW.md` §8).
  The product is a responsive **server-rendered web application**, not a SPA and
  not a native mobile app.
- **No business rules in the browser.** Authorization, tenancy, validation of
  record, and money/limit decisions are **server** concerns. The client MAY mirror
  them for UX, but the server is the single source of truth.

```html
<!-- EXAMPLE ONLY — illustrative Alpine usage for a light UI toggle -->
<div x-data="{ open: false }">
  <button @click="open = !open" :aria-expanded="open">Filters</button>
  <div x-show="open" x-cloak>…</div>
</div>
```

## 5. Design Tokens, Theming & Branding

The UI is built on a **design-token system** so the product looks like one product
and can be themed per workspace.

- **Token categories (binding):** color, spacing, typography (family, size, weight,
  line-height), border radius, shadow/elevation, and z-index layering. Components
  MUST consume tokens — they MUST NOT hard-code raw values.
- **Tailwind is configured from the tokens.** The token set is the single source;
  Tailwind's theme is derived from it so utilities and tokens never drift.
- **Per-workspace branding.** Each workspace owns its branding — **logo, cover,
  colors, favicon, email branding** (`WORKSPACE_MODEL.md` §2, §4). The UI MUST
  apply the active workspace's brand (e.g. via CSS custom properties resolved at
  render) **without** forking templates or components. Branding never crosses the
  tenant boundary: one workspace's brand MUST NOT appear in another.
- **Dark mode readiness.** Tokens MUST be defined so a dark theme is achievable
  without rewriting components (semantic tokens, not literal colors). Full dark
  mode delivery is detailed in Phase 5, but Phase-1 token design MUST NOT preclude
  it.
- **Semantic tokens over literal ones.** Prefer `surface`, `text-muted`, `border`,
  `danger`, `success` over raw color names, so theming and dark mode are a token
  swap, not a refactor.

## 6. Bilingual & RTL/LTR — AR/EN From Day One

The product UI is **bilingual Arabic/English with full RTL/LTR support from day
one** (Constitution §3, §12). This is a **first-class architectural property, not
a translation layer bolted on later**.

- **No hard-coded user-facing strings.** All product copy MUST come from the
  localization catalogs (`/resources/lang/ar`, `/resources/lang/en` —
  Constitution §8). A literal string in a template is a defect.
- **Direction-aware layouts.** Layouts MUST adapt to text direction. Use
  **logical, direction-aware** styling (start/end, not hard left/right) so the
  same markup renders correctly in both **RTL (Arabic)** and **LTR (English)**.
  The document `dir` and `lang` MUST reflect the active language.
- **Mirroring.** Directional UI (icons that imply direction, progress, breadcrumb
  chevrons, drawers, the sidebar) MUST mirror correctly under RTL.
- **Localized formatting.** Dates, numbers, and currency MUST be formatted per the
  active locale and the **workspace settings** (timezone, language, currency, date
  format — `WORKSPACE_MODEL.md` §4). The UI MUST NOT assume a single locale's
  formatting.
- **Parity.** AR and EN are peers. Neither is a fallback for missing work in the
  other; both MUST be complete for a shipped screen.

> Engineering documentation is written in English (Constitution §12); this rule is
> about the **product UI**, which is bilingual.

## 7. Component Conventions & Shared View Structure

The UI is assembled from a **shared, reusable component set** and a consistent
view/partial structure, so modules compose rather than reinvent.

- **Shared layouts and partials.** Common layouts, the (single) sidebar, headers,
  empty states, tables, forms, buttons, badges, modals, and toasts live as
  **shared views/partials** (`/resources` for cross-cutting, module `Resources/`
  for module-specific — Constitution §8; `ARCHITECTURE.md` §3). Modules MUST reuse
  these before creating their own.
- **View-models, not logic in templates.** Templates render data prepared by the
  Presentation layer's **view-models** (`ARCHITECTURE.md` §2). Templates MUST NOT
  run queries, contain business rules, or branch on roles.
- **Consistent component contract.** A component MUST expose a clear, documented
  set of inputs and behave identically across modules. The same component MUST NOT
  be re-implemented per module.
- **Naming & structure.** Follow the project's naming and folder standards
  (Constitution §7, §8). Module views live under the module's `Resources/views/`;
  shared views under `/resources`.
- **Accessibility & i18n are built into components**, not added per screen (see §6,
  §9). A shared component is the right place to get focus order, ARIA, and
  direction-awareness correct once.

## 8. Mandatory Screen States

Every screen and data-bearing component MUST deliberately handle the following
states. Designing only the "happy path" is a defect. A view is incomplete until
each applicable state is defined.

| State | Requirement |
|---|---|
| **First-use / empty** | A meaningful empty state for first use or no data: what this is, and the next action (subject to permission). Never a blank screen. |
| **Loading** | Clear, non-blocking loading feedback (e.g. skeletons/placeholders). Layout MUST NOT jump when content arrives. |
| **No-permission** | When the user lacks the required permission, show a clear, non-leaking message — never a broken page, and never the data. UI hiding complements, never replaces, server enforcement (`PERMISSION_MODEL.md` §5). |
| **Error** | Human-readable, actionable error messaging. No stack traces or internal details are ever shown to the user (`ARCHITECTURE.md` §6). |
| **Success** | Explicit confirmation of state-changing actions (toast/inline), with the UI reflecting the new state. |
| **Offline / degraded** | Detect loss of connectivity or a degraded backend and inform the user; avoid silent failures and protect against double-submission. |

These states MUST be consistent across modules and SHOULD be provided by shared
components so each module inherits correct behavior.

## 9. Accessibility Baseline

Accessibility is a **Phase-1 baseline, not a later phase**. Every shipped screen
MUST meet at least the following:

- **Keyboard navigation.** All interactive elements MUST be reachable and operable
  by keyboard alone, in a logical order. No keyboard traps.
- **Focus management.** Focus MUST be visible and managed deliberately — moved into
  opened modals/drawers and restored on close.
- **ARIA & semantics.** Use correct semantic HTML first; add ARIA roles, names, and
  states where semantics are insufficient. Icon-only controls MUST have accessible
  names (also localized — §6).
- **Color contrast.** Text and essential UI MUST meet **WCAG 2.1 AA** contrast.
  Color MUST NOT be the **only** carrier of meaning (pair with text/icon).
- **Screen readers.** Content MUST be navigable and understandable with a screen
  reader; dynamic updates (toasts, async results) MUST be announced appropriately.
- **Direction-aware a11y.** Accessibility MUST hold equally in **AR/RTL** and
  **EN/LTR** (§6).

The depth of WCAG conformance and audit process is expanded in Phase 5; this
baseline is binding now.

## 10. Performance

The UI MUST honor the platform's performance posture (Constitution §11). UI-side
budgets and the server p95 budgets are complementary.

- **Minimal JavaScript.** Ship as little JS as possible (§4). Prefer
  server-rendered HTML + progressive enhancement over client-heavy patterns. Avoid
  large client dependencies.
- **Asset bundling & minification.** CSS/JS MUST be bundled and minified for
  production; Tailwind output MUST be **purged** to the classes actually used.
  Assets SHOULD be fingerprinted/cache-busted and served with long cache headers.
- **Lazy loading.** Defer non-critical assets, images (`loading="lazy"`), and
  below-the-fold or rarely-used UI. Heavy or AI-backed work runs **asynchronously**
  on the server (Constitution §11; `ARCHITECTURE.md` §8) — the UI MUST stay
  responsive and reflect async progress via the defined screen states (§8).
- **No layout thrash.** Reserve space for async content to prevent layout shift
  (ties to the Loading state, §8).
- **Server budget awareness.** Server-rendered pages target **p95 < 300 ms**
  (Constitution §11); the UI MUST NOT introduce client work that undermines a fast
  first render.

---

## Self-Review Checklist (Phase 1 UI gate)

- [ ] No role-based sidebars; navigation derives from user + workspace +
      permissions + subscription + enabled modules.
- [ ] No user-facing string is hard-coded; AR/EN catalogs cover the screen.
- [ ] Layout is direction-aware (logical start/end); renders correctly in RTL & LTR.
- [ ] All six screen states are handled (empty, loading, no-permission, error,
      success, offline).
- [ ] Keyboard nav, focus management, ARIA, and AA contrast are satisfied.
- [ ] Styling uses tokens via Tailwind; per-workspace branding applies without
      forking components; dark mode is not precluded.
- [ ] Alpine is used only for light interactivity; no SPA framework; no business
      logic in the browser.
- [ ] Assets are bundled, minified, Tailwind-purged; non-critical assets lazy-load.

---

### Related Documents

`PROJECT_CONSTITUTION.md` · `SYSTEM_OVERVIEW.md` · `WORKSPACE_MODEL.md` ·
`PERMISSION_MODEL.md` · `USER_MODEL.md` · `ARCHITECTURE.md` · `MODULES.md` ·
`DOMAIN_MODEL.md` · `NAVIGATION_ARCHITECTURE.md` · `SIDEBAR_MODEL.md` ·
`SCREEN_CATALOG.md`
