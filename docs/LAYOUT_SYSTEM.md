# LAYOUT SYSTEM — HaHireAI

> **Status:** Adopted (Phase 5) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `UI_GUIDELINES.md`, `PROJECT_CONSTITUTION.md`.

---

## 0. About This Document

This document defines the **layout system** of HaHireAI — the application shell,
the responsive grid, the design-token foundation, the overlay stack, and the
theming hooks that every screen inherits. It is the structural counterpart to
`UI_GUIDELINES.md` (principles) and `PAGE_STANDARDS.md` (per-page anatomy).

This document **defers** to `UI_GUIDELINES.md` and, above it, to
`PROJECT_CONSTITUTION.md` (the supreme reference). It does not re-litigate the
stack (Native PHP 8.3+, MySQL 8+, TailwindCSS, Alpine.js for light UI only,
Vanilla JS — Constitution §5) or the navigation model (one dynamic sidebar —
`UI_GUIDELINES.md` §2, `NAVIGATION_MAP.md` §1). Interpretation keywords
(**MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, **MAY**) follow RFC 2119,
exactly as in the Constitution. A rule written **MUST / MUST NOT** is binding; a
violation is a defect.

> Markup and class snippets in this document are **illustrative examples only**.
> They are not the implementation and MUST NOT be copied as canonical code.
> Token names below are the contract; literal Tailwind utilities shown are
> indicative.

---

## 1. The Application Shell

HaHireAI renders inside **one application shell** shared by every authenticated
screen and by both operating contexts (Platform and Workspace —
`UI_GUIDELINES.md` §3). The shell is **server-rendered** as a single layout
partial; client behavior is progressive enhancement only (Constitution §4,
`UI_GUIDELINES.md` §4). Modules MUST render into this shell and MUST NOT define
their own competing chrome.

The shell has four regions:

```
┌──────────────────────────────────────────────────────────────┐
│  TOP BAR  (context switcher · search · notifications · user)   │  ← region 1
├───────────┬──────────────────────────────────────┬───────────┤
│           │                                        │           │
│  SIDEBAR  │           CONTENT REGION               │  RIGHT    │
│  (one,    │   (page header + page body —           │  PANEL    │
│  dynamic) │    see PAGE_STANDARDS.md)              │  (opt.)   │
│           │                                        │           │
└───────────┴──────────────────────────────────────┴───────────┘
   region 2              region 3                      region 4
```

1. **Top bar** — fixed to the viewport top, full width. It carries: the
   **workspace / context switcher** (`UI_GUIDELINES.md` §3, `NAVIGATION_MAP.md`
   §2), the workspace brand mark, **global search** and **notifications** (both
   always present per `NAVIGATION_MAP.md` §7.2), and the user menu. The active
   context MUST be unmistakable here. The top bar MUST remain visible while the
   content scrolls.
2. **Sidebar** — exactly **one**, generated dynamically from `(user, workspace,
   permissions, subscription, enabled modules)` (`UI_GUIDELINES.md` §2). There
   are **no role-based sidebars**. The sidebar is a structural region here; its
   composition rules are owned by `UI_GUIDELINES.md` §2 and `SIDEBAR_MODEL.md`.
3. **Content region** — the primary scroll container. It hosts every page's
   header and body (`PAGE_STANDARDS.md` §1). It MUST be the only region that
   owns the main scrollbar in the default layout, so the shell stays fixed.
4. **Right panel** — an **optional** contextual region for detail/inspector,
   AI-assist, activity timelines, or comments. It MUST be dismissible, MUST NOT
   be required to operate a page, and MUST collapse first under width pressure
   (§3). When absent, the content region reclaims its width.

The shell MUST occupy exactly the viewport height with no double scrollbars. The
content region scrolls independently; the top bar and sidebar do not scroll with
it.

---

## 2. Responsive Grid & Spacing Scale

### 2.1 Spacing scale (4 px base)

Spacing is a **token scale** on a 4 px base unit. Components MUST consume scale
tokens and MUST NOT hard-code arbitrary pixel margins/padding (`UI_GUIDELINES.md`
§5).

| Token | Value | Typical use |
|---|---|---|
| `space-0` | 0 | reset |
| `space-1` | 4 px | icon ↔ label, tight inline gaps |
| `space-2` | 8 px | control inner padding, chip gaps |
| `space-3` | 12 px | compact stacks, table cell padding |
| `space-4` | 16 px | default element gap, card padding |
| `space-5` | 20 px | grouped controls |
| `space-6` | 24 px | section padding, page gutter (mobile) |
| `space-8` | 32 px | between page sections |
| `space-10` | 40 px | page gutter (desktop) |
| `space-12` | 48 px | major separators, empty-state padding |
| `space-16` | 64 px | hero / first-use spacing |

### 2.2 Layout grid

- The content region uses a **12-column** fluid grid for page-level composition.
  Forms, cards, and dashboards MUST align to this grid rather than to ad-hoc
  widths.
- A **max content width** token (`content-max`, ~1280 px) MUST cap reading and
  form measures on very wide screens; the content centers within the region
  beyond that width. Full-bleed surfaces (data tables, Kanban boards) MAY opt out
  via a documented `full-bleed` affordance.
- **Gutters** are token-driven (`space-6` mobile → `space-10` desktop, §3) and
  MUST mirror under RTL (§4).
- Column **gaps** use the spacing scale (`space-4`/`space-6`). Components MUST NOT
  invent bespoke gaps.

---

## 3. Breakpoints & Shell Behavior (Desktop / Tablet / Mobile)

Breakpoints are tokens; layout decisions MUST reference them, not raw widths.
HaHireAI is **responsive web** (not a native app — `UI_GUIDELINES.md` §4) and
MUST be fully operable across the range below.

| Token | Min width | Tier |
|---|---|---|
| `bp-sm` | 640 px | large phone |
| `bp-md` | 768 px | tablet (portrait) |
| `bp-lg` | 1024 px | tablet (landscape) / small laptop |
| `bp-xl` | 1280 px | desktop |
| `bp-2xl` | 1536 px | wide desktop |

**Desktop (`≥ bp-xl`).** Full three/four-region shell. Sidebar **expanded** by
default (label + icon). Right panel MAY be docked. Page gutter `space-10`.

**Tablet (`bp-md`–`bp-lg`).** Sidebar **collapses to a rail** (icons only) by
default; hovering/focusing a rail item reveals its label and any flyout
sub-items. The right panel MUST become an **overlay drawer** rather than a docked
column. Page gutter `space-6`.

**Mobile (`< bp-md`).** Sidebar is **off-canvas**: hidden by default, opened as a
full-height drawer over a scrim from a top-bar control. The top bar MAY condense
secondary actions into an overflow menu. Content is single-column; the right
panel is always an overlay. Tap targets MUST be ≥ 44 × 44 px.

Binding shell rules:

- The sidebar's **expanded / rail / off-canvas** state MUST be derived from the
  breakpoint by default, and the user's manual collapse preference SHOULD persist
  per session/device. Server-rendered HTML MUST choose a sensible initial state
  so the shell is usable before JS loads (no layout flash).
- Collapsing the sidebar MUST NOT remove navigation capability — every item
  reachable when expanded MUST remain reachable when collapsed (via tooltip
  labels and flyouts), satisfying the keyboard and a11y baseline
  (`UI_GUIDELINES.md` §9).
- Region transitions MUST avoid layout thrash; reserve space for async content
  (`UI_GUIDELINES.md` §10).

---

## 4. RTL / LTR Mirroring Rules

The shell is **bidirectional from day one** (Constitution §3, §12;
`UI_GUIDELINES.md` §6). Direction is set on the document (`dir` + `lang`) from the
active language and MUST flow through the entire shell.

- **Logical properties only.** Layout MUST use direction-aware
  start/end semantics (logical margins, padding, insets, `text-align: start`),
  never hard-coded left/right. This applies to the grid gutters, sidebar
  placement, and panel docking.
- **Sidebar side mirrors.** In LTR the sidebar sits at the inline **start**
  (left); in RTL it mirrors to the inline **start** (right) automatically. The
  right panel sits at the inline **end** in both directions.
- **Directional iconography mirrors.** Chevrons, breadcrumb separators, back/next
  arrows, progress, and drawer slide direction MUST flip under RTL
  (`UI_GUIDELINES.md` §6). Non-directional icons (search, user, bell) MUST NOT
  flip. Bidirectional-neutral media (logos, avatars) MUST NOT flip.
- **Overlays follow direction.** Drawers slide from the inline start/end per
  direction; popovers and dropdowns align to the inline start of their trigger
  and flip alignment under RTL.
- **Numerals & formatting** follow the active locale and workspace settings
  (timezone, language, currency, date format — `WORKSPACE_MODEL.md` §4); the
  layout MUST NOT assume a single locale's digit shaping or width.
- **Parity.** RTL and LTR are peers; a screen is incomplete until it is correct
  in both (`UI_GUIDELINES.md` §6).

```html
<!-- EXAMPLE ONLY — logical, direction-aware spacing; mirrors under [dir=rtl] -->
<aside class="ps-4 pe-2 border-e"><!-- start/end, never left/right --></aside>
```

---

## 5. Design Tokens

Tokens are the **single source of truth** for visual values; Tailwind's theme is
**derived from the tokens** so utilities and tokens never drift
(`UI_GUIDELINES.md` §5). Components MUST consume **semantic** tokens and MUST NOT
hard-code raw values. Semantic tokens resolve to a palette and, in turn, to the
active theme (§9) — enabling per-workspace branding and dark mode by **token
swap, not refactor**.

### 5.1 Color (semantic)

| Semantic token | Role |
|---|---|
| `color-bg` / `color-surface` / `color-surface-raised` | app background, cards, raised panels |
| `color-text` / `color-text-muted` / `color-text-subtle` | primary, secondary, tertiary text |
| `color-border` / `color-border-strong` | dividers, control borders |
| `color-primary` / `color-primary-contrast` | brand action, text on brand |
| `color-success` / `color-warning` / `color-danger` / `color-info` | status families (each with a `-subtle` surface + `-contrast` text) |
| `color-focus-ring` | focus indicator (AA-visible, §8, a11y) |

Status MUST NOT be conveyed by color alone — pair with icon/text
(`UI_GUIDELINES.md` §9). Brand-bearing tokens (`color-primary`) are the primary
hooks per-workspace branding overrides (§8).

### 5.2 Typography

| Token | Role |
|---|---|
| `font-sans` | UI font family (MUST include an Arabic-capable face for AR parity, §4) |
| `text-xs … text-3xl` | type ramp (12 / 14 / 16 / 18 / 20 / 24 / 30 px) |
| `font-normal / -medium / -semibold / -bold` | weight scale |
| `leading-tight / -normal / -relaxed` | line-height scale |

Base body size MUST be ≥ 14 px; line length on text content SHOULD respect
`content-max` (§2.2). Font loading MUST NOT block first render (`UI_GUIDELINES.md`
§10).

### 5.3 Radius, elevation, motion

| Category | Tokens |
|---|---|
| **Radius** | `radius-sm` (4) · `radius-md` (8) · `radius-lg` (12) · `radius-full` (pill/avatar) |
| **Elevation** | `shadow-0` (flat) · `shadow-1` (card) · `shadow-2` (popover/dropdown) · `shadow-3` (modal/drawer) — elevation MUST track the z-index layer (§7) |
| **Motion** | `motion-fast` (~120 ms) · `motion-base` (~200 ms) · `motion-slow` (~320 ms); standard easing token. All motion MUST honor `prefers-reduced-motion` and degrade to instant. |

### 5.4 Z-index layers

Stacking is a **fixed token ladder** (§7); no component may invent an arbitrary
`z-index`.

| Token | Band | Occupants |
|---|---|---|
| `z-base` | 0 | normal content flow |
| `z-sticky` | 100 | sticky table headers, sticky page header |
| `z-shell` | 200 | top bar, docked sidebar |
| `z-dropdown` | 300 | popovers, menus, comboboxes, tooltips |
| `z-overlay` | 400 | drawer/modal scrim |
| `z-modal` | 500 | modal dialog, drawer surface |
| `z-toast` | 600 | toasts / transient notifications |
| `z-max` | 900 | reserved (dev/diagnostic overlays only) |

---

## 6. Overlay Layers & Stacking

Overlays are **shared components** so behavior is identical across modules
(`UI_GUIDELINES.md` §7). Four overlay types exist; each binds to a fixed z-band
(§5.4):

- **Modal** (`z-modal`, scrim `z-overlay`) — blocking, focus-trapped task or
  confirmation. Detailed contract in `PAGE_STANDARDS.md`.
- **Drawer** (`z-modal`, scrim `z-overlay`) — slide-in panel from the inline
  start/end (RTL-aware, §4). The optional **right panel** (§1) becomes a drawer
  on tablet/mobile (§3).
- **Popover / dropdown / tooltip / combobox** (`z-dropdown`) — non-blocking,
  anchored to a trigger, dismiss on outside-click/Escape, no scrim.
- **Toast** (`z-toast`) — transient, non-blocking feedback, stacked in a single
  region (§ `PAGE_STANDARDS.md` toast standards).

Binding stacking rules:

- Layering MUST follow the z-token ladder; higher-intent surfaces sit above
  lower ones (toast > modal > scrim > dropdown > shell). A component MUST NOT
  hard-code a numeric `z-index`.
- **One blocking layer at a time.** Stacking modal-on-modal SHOULD be avoided;
  when genuinely required, focus trap and Escape MUST apply to the topmost layer
  only, and the scrim MUST sit directly beneath it.
- **Focus management.** Opening a modal/drawer MUST move focus into it and
  restore focus to the trigger on close; focus MUST be trapped while open
  (`UI_GUIDELINES.md` §9).
- **Scroll lock.** While a scrim is shown, background scroll MUST be locked
  without layout shift (compensate for scrollbar width).
- **Escape & scrim** dismiss non-destructive overlays; destructive flows MUST
  require explicit confirmation rather than dismiss-to-confirm
  (`PAGE_STANDARDS.md`).
- Toasts MUST NOT obscure the primary action of the surface beneath; they sit
  clear of the inline-end edge and respect safe areas on mobile.

---

## 7. Stacking Model (summary)

The combined **paint order** of the shell, from back to front, is the single
source for any new surface:

```
content (z-base)
  → sticky headers (z-sticky)
    → top bar / sidebar (z-shell)
      → menus / popovers / tooltips (z-dropdown)
        → scrim (z-overlay)
          → modal / drawer (z-modal)
            → toasts (z-toast)
              → [reserved diagnostics] (z-max)
```

Any new overlay MUST slot into an existing band; introducing a new band requires
an update to this document and to the token set (`UI_GUIDELINES.md` §5).

---

## 8. Density Modes & Per-Workspace Theming

### 8.1 Density modes

The layout supports two **density modes** to serve both data-dense operations
(large pipelines, tables) and comfortable reading:

| Mode | Intent | Effect (token-driven) |
|---|---|---|
| **Comfortable** (default) | general use | larger row heights, `space-4`+ paddings |
| **Compact** | power users, dense tables/Kanban | reduced row height & vertical padding (one step down the scale), unchanged type ramp for legibility |

- Density MUST be implemented by **switching spacing/row tokens**, never by
  forking components or templates.
- Density is a **user preference** that SHOULD persist per session/device and
  MUST NOT change information, only spacing. Tap targets MUST remain ≥ 44 px on
  touch even in Compact (§3).
- Density MUST NOT reduce focus-ring visibility or contrast (§5, a11y).

### 8.2 Per-workspace branding & theming hooks

Each workspace owns its branding — logo, cover, colors, favicon, email branding
(`WORKSPACE_MODEL.md` §2, §4; `UI_GUIDELINES.md` §5). The layout exposes
**theming hooks**, not template forks:

- Branding MUST be applied by **resolving semantic tokens to the active
  workspace's brand at render** (e.g. CSS custom properties emitted into the
  shell root), so one shell serves every tenant.
- **Brand tokens** (primary color + contrast, logo/favicon refs) override only
  the brand-bearing semantic tokens (§5.1). Structural, status, and neutral
  tokens are **not** workspace-overridable (consistency is a requirement —
  `UI_GUIDELINES.md` §1).
- **Tenant isolation of brand.** One workspace's brand MUST NOT appear in another
  (`UI_GUIDELINES.md` §5). Switching context (§1) MUST re-resolve brand tokens for
  the new context; the **Platform Context** uses the platform's own neutral brand.
- Overridden brand colors MUST still satisfy **WCAG 2.1 AA** contrast against
  their paired surfaces; the system SHOULD validate contrast when a workspace sets
  a brand color and warn on failure (`UI_GUIDELINES.md` §9).

```css
/* EXAMPLE ONLY — brand hook resolved per workspace at render (illustrative) */
:root[data-workspace-brand] {
  --color-primary: var(--brand-primary, /* platform default */ );
}
```

---

## 9. Dark Mode Readiness

Dark mode is a **first-class readiness requirement**, even where full delivery is
scheduled later (`UI_GUIDELINES.md` §5).

- All surfaces MUST consume **semantic** tokens (§5.1) so a dark theme is a token
  swap, not a component rewrite. Hard-coded light-only colors are a defect.
- The system MUST support theme selection of **light / dark / system** as a user
  preference, resolved at render and stable across navigation (no flash of
  incorrect theme). Server-rendered HTML MUST emit the correct theme attribute on
  first paint.
- **Elevation in dark** uses surface-tint tokens (raised surfaces lighten) rather
  than relying solely on shadows; `shadow-*` tokens MUST remain meaningful in both
  themes.
- Theme and **per-workspace brand** (§8.2) compose: brand tokens resolve within
  the active theme, and both MUST preserve AA contrast (§5, a11y).
- Dark mode MUST hold equally in RTL/LTR (§4) and in both density modes (§8.1).

---

## 10. Conformance Rules (binding summary)

- The shell is **one server-rendered layout** with four regions (top bar,
  dynamic sidebar, content, optional right panel); modules render into it and MUST
  NOT define competing chrome (§1).
- Spacing, sizing, color, type, radius, elevation, motion, and z-index come from
  **tokens**; raw values are a defect (§2, §5).
- Layout is **direction-aware** (logical start/end) and correct in RTL and LTR
  (§4).
- Responsive behavior follows the breakpoint tokens; the sidebar degrades
  expanded → rail → off-canvas without losing navigation (§3).
- Overlays use the **shared components** and the **fixed z-ladder**; focus,
  scroll-lock, and Escape behavior are honored (§6, §7).
- Density modes and per-workspace branding are achieved by **token swaps**, never
  template forks; brand never crosses the tenant boundary (§8).
- Tokens are defined so **dark mode** is achievable without rewriting components
  (§9).

---

## Self-Review Checklist (Layout gate)

- [ ] Page renders inside the shared shell; no module-specific chrome.
- [ ] No raw pixel/color/z-index values — all from tokens.
- [ ] Correct in RTL and LTR using logical properties; directional icons mirror.
- [ ] Sidebar collapses correctly per breakpoint; all nav reachable when
      collapsed.
- [ ] Right panel collapses to an overlay on tablet/mobile and is dismissible.
- [ ] Overlays slot into the z-ladder; focus trap, focus restore, and scroll-lock
      work.
- [ ] Comfortable & Compact density both legible; tap targets ≥ 44 px on touch.
- [ ] Per-workspace brand applies via token resolution; does not leak across
      tenants; meets AA contrast.
- [ ] Semantic tokens only — dark mode not precluded; no flash of incorrect theme.

---

### Related Documents

`UI_GUIDELINES.md` · `PROJECT_CONSTITUTION.md` · `PAGE_STANDARDS.md` ·
`WORKSPACE_MODEL.md` · `NAVIGATION_MAP.md` · `SIDEBAR_MODEL.md` ·
`NAVIGATION_ARCHITECTURE.md` · `SCREEN_CATALOG.md`
