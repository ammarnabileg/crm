# 30 — Frontend Architecture (معمارية الواجهة الأمامية)

How HalaOps renders its UI: server-rendered plain-PHP templates through the View engine, a pre-compiled Tailwind stylesheet (no build on the buyer's server), vanilla-JS progressive enhancement, and native bilingual RTL/LTR rendering.

## Related Documents

- [29 — API Architecture](29-API-Architecture.md)
- [31 — Backend Architecture](31-Backend-Architecture.md)
- [03 — System Architecture](03-System-Architecture.md)
- [04 — Folder Structure](04-Folder-Structure.md)
- [34 — Security](34-Security.md)

---

## Purpose (الهدف)

This document specifies the **frontend architecture** of HalaOps: the templating model (server-rendered PHP via `App\Core\View`), layout inheritance with sections, the partials/components approach, the compiled Tailwind CSS pipeline, the vanilla-JS progressive-enhancement layer, asset versioning, accessibility, and — central to the product — **bilingual Arabic/English RTL/LTR rendering**. It is the contract for anyone building or editing a screen.

## Why It Exists (سبب وجوده)

HalaOps targets buyers on **shared hosting with no Node/npm/terminal**. That makes a JavaScript SPA build pipeline (Webpack/Vite, `npm run build`) a non-starter on the buyer's machine. The product is also **bilingual and RTL-native**, which most JS UI stacks treat as an afterthought. So the frontend is deliberately:

- **Server-rendered** — HTML is produced by PHP templates, so there is nothing to build at install time and the app works with JavaScript disabled.
- **Tailwind compiled offline** — the design system's power without a server build step; the compiled `public/assets/css/app.css` ships in the repo.
- **Progressively enhanced** — a tiny vanilla `app.js` adds nicety (dropdowns, confirmations) on top of fully-functional HTML, never as a dependency for core flows.
- **RTL/LTR from the root element** — direction and language are decided once in the layout, so every screen is correct in Arabic and English without per-component effort.

This mirrors the canonical principle: *Tailwind CSS compiled to `public/assets/css/app.css`; no build step on the buyer's server, vanilla JS, server-rendered PHP templates.*

## Architecture

### Rendering stack

```mermaid
flowchart TB
    Controller["Controller → view('app.dashboard', data)"]
    Helper["view() / render() helper"]
    Engine["App\\Core\\View (engine)"]
    Child["Child template (resources/views/app/dashboard.php)"]
    Layout["Layout (layouts/app.php or layouts/guest.php)"]
    Partials["Partials (partials/alerts.php)"]
    HTML["HTML string → Response"]
    CSS["public/assets/css/app.css (compiled Tailwind)"]
    JS["public/assets/js/app.js (defer)"]

    Controller --> Helper --> Engine
    Engine --> Child
    Child -->|"\$this->extends('layouts.app')"| Layout
    Child -->|"\$this->section('content')..."| Layout
    Layout -->|"\$this->include('partials.alerts')"| Partials
    Layout --> HTML
    HTML -. "<link>" .-> CSS
    HTML -. "<script defer>" .-> JS
```

### The View engine (`App\Core\View`)

A compile-free, plain-PHP template engine. Templates are PHP files under `resources/views/` addressed in dot notation (`auth.login` → `resources/views/auth/login.php`). The engine exposes a small directive set, available as `$this->...` inside any template because each file is `include`d in the engine's object scope:

| Directive | Effect |
|---|---|
| `$this->extends('layouts.app')` | Declare the parent layout; the child's output becomes the layout's `content`. |
| `$this->section('name')` … `$this->endSection()` | Capture a named block (output-buffered). |
| `$this->yield('name', $default)` | Emit a captured section in the layout. |
| `$this->has('name')` | Whether a section was declared. |
| `$this->include('partial', $data)` | Render a partial inline (shares globals + passed data). |

Key behaviours from the implementation:
- **Isolation per render.** `render()` snapshots and resets section/layout state, so nested renders (a partial that itself renders) don't clobber the parent.
- **Implicit content.** A child may either wrap its body in `section('content')`/`endSection()` **or** just echo directly; if no explicit `content` section is declared, the child's raw output is used as `content`. An explicit section is never overwritten.
- **Shared globals.** `View::share()` injects values available to every template; the kernel shares `view` (the engine itself, for `$this->...`) and `appName`.
- **Escaped extraction.** Data is `extract(..., EXTR_SKIP)`ed into the template scope; output is escaped by the template author via the `e()` helper.

### Layouts

Two layouts cover the whole product:

- **`layouts/guest.php`** — auth screens, the installer, and public/landing pages. Minimal: sets `<html lang dir>`, loads the stylesheet/favicon, yields `content` and `scripts`.
- **`layouts/app.php`** — the authenticated application shell: a permission-aware sidebar, a topbar with the workspace switcher, the language toggle, the user menu, the flash-message partial, and the `content`/`scripts` yields.

The authenticated shell drives the **navigation registry** — an array of `[path, label, permission, built?]` entries. A link renders **only** when the feature is `built` **and** `can($permission)` is true, so the UI never shows dead links or unauthorized sections:

```php
$nav = [
    ['dashboard', 'Dashboard',            'dashboard.view', true],
    ['members',   'Members',              'members.view',   false],
    ['roles',     'Roles & Permissions',  'roles.view',     false],
    ['ai',        'AI Settings',          'ai.view',        false],
    ['billing',   'Billing',              'billing.view',   false],
    ['settings',  'Workspace Settings',   'settings.view',  false],
];
```

### The Design System & component library

HalaOps ships **one unified Design System** (docs/30): design tokens, a reusable
component library, light/dark theming, and standard states — so every screen looks
and behaves consistently and nothing is hand-rolled twice.

**Design tokens.** Semantic roles (`primary`/`success`/`warning`/`danger`/`info`,
plus the legacy `brand` alias) with full 50–950 scales, radius, z-index layers
(`dropdown`→`toast`), transitions and animations live in `tailwind.config.js`, and
the key roles are mirrored as CSS variables in `resources/css/app.css` (`:root`).
Templates reference tokens (`bg-brand-600`, `text-success-700`, `z-modal`), never
raw hex.

**Component library.** Reusable UI lives in `resources/views/components/*.php` —
each a self-contained PHP partial with a `Purpose / Props / States / Usage`
docblock that reads its `$props` with sane defaults and escapes every dynamic value
with `e()`. Render one with the `component()` helper, which returns HTML so it
composes:

```php
<?= component('button', ['label' => 'Save', 'type' => 'submit']) ?>
<?= component('field', [
    'label' => 'Title', 'for' => 'title', 'name' => 'title',
    'control' => component('input', ['name' => 'title', 'id' => 'title']),
]) ?>
```

The shipped set (29 components): **forms** — `button`, `input`, `textarea`,
`select`, `checkbox`, `radio`, `switch`, `field`; **display** — `avatar`, `badge`,
`chip`, `card`, `stat`, `table`, `accordion`, `breadcrumb`, `pagination`,
`page-header`, `tabs`; **feedback** — `alert`, `toast`, `progress`, `skeleton`,
`spinner`, `tooltip`; **overlays** — `modal`, `drawer`, `dropdown`; and `state`
(the unified Empty / Error / Permission-denied / Offline / Loading screen). The
`attrs()` helper forwards arbitrary, escaped attributes from a component's
`attributes` prop (`true` → valueless, `null`/`false` → dropped).

The component model is **"PHP partial + Tailwind classes"**, not a JS framework.
`resources/views/partials/` still holds page-level fragments (e.g.
`partials/alerts.php`); the difference is that `components/` are parameterised,
reusable primitives.

**Living catalog.** `GET /design` (`DesignSystemController` → `app/design.php`,
gated by `dashboard.view`) renders every component with its variants and states
inside the real app shell — the canonical, always-current style guide for
reviewing light/dark, RTL/LTR and accessibility in one place.

### Styling — compiled Tailwind

- **Source:** `resources/css/app.css` (Tailwind directives + the `@layer components` Design System classes — `.btn-*`, `.input`, `.card`, `.badge-*`, `.alert-*`, `.tab`, `.modal-panel`, `.drawer-panel`, `.state`, …) and `tailwind.config.js` (tokens).
- **Compiled output:** `public/assets/css/app.css`, committed to the repo and shipped as-is. **No build runs on the buyer's server** (`npm run build:css` is a dev-only step).
- **Linked once** in each layout: `<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">`.
- **Design tokens** (semantic colour scales, radius, z-index, transitions, keyframes) are defined once in `tailwind.config.js` and consumed via utilities/component classes — no hard-coded values in templates.
- **Dark mode** is class-driven (`darkMode: 'class'`): a no-flash `<head>` script applies the saved/system theme to `<html>` before first paint, the topbar `data-theme-toggle` flips it (persisted in `localStorage`), and every component carries `dark:` variants — **one component set, two themes** (no duplicate dark templates).

### JavaScript — vanilla progressive enhancement

`public/assets/js/app.js` is a single IIFE, loaded with `defer`. It is **enhancement, not infrastructure** — every page works without it. All wiring is **data-attribute driven and delegated**, so server-rendered *and* dynamically-injected markup behave identically. It provides:
- **Overlays** — `data-modal-open`/`data-drawer-open` open a dialog by id; `×`, backdrop or `Escape` close it; focus moves into the panel and is restored on close; body scroll is locked while any overlay is open.
- **Tabs** — `data-tab-target` switches panels with full WAI-ARIA arrow-key navigation (RTL-aware).
- **Switch** — `data-switch` toggles `aria-checked` and syncs the hidden input so the state submits; operable with Space/Enter.
- **Toasts** — `window.HalaToast(message, {variant, title, timeout})` mounts an auto-dismissing notification into `#toast-region`; message text is set via `textContent` (never `innerHTML`), and `data-toast-demo` offers a no-inline-JS trigger.
- **Dark mode** — `data-theme-toggle` flips `.dark` on `<html>` and persists the choice.
- **Dismissals** — `data-alert-dismiss` / `data-chip-remove` / `data-toast-dismiss`; plus the original `<details>` close-on-outside-click/`Escape` and `data-confirm` submit guard.

There is no bundler, no framework, no inline event handlers. New behaviour is added as small, delegated listeners in the same file.

### Asset versioning

The `asset()` helper appends a cache-busting query string from `config('app.asset_version')` (env `ASSET_VERSION`, default `1.0.0`): `…/assets/css/app.css?v=1.0.0`. Bumping `ASSET_VERSION` after shipping a new compiled bundle forces browsers to refetch CSS/JS without renaming files.

### Bilingual RTL/LTR rendering

Direction and language are set **once, on the root element**, in both layouts:

```php
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
```

- `locale()` and `is_rtl()` come from `App\Core\Translator`; Arabic ⇒ `dir="rtl"`, English ⇒ `dir="ltr"`.
- Templates use **logical/flow-relative** Tailwind utilities (`border-e`, `border-s`, `text-start`, `end-0`, `ms-*`, `me-*`) so the same markup mirrors correctly in RTL — there are no hard-coded `left`/`right` assumptions.
- Translatable strings are emitted via `__()` from `resources/lang/{en,ar}/`.
- The **language toggle** in the topbar links to the current path with `?lang=ar`/`?lang=en`; the kernel's `resolveLocale()` stores the choice in the session and `setLocale()`s the translator for the rest of the request (and subsequent ones).

## Workflow

### Rendering a page (sequence)

```mermaid
sequenceDiagram
    participant C as Controller
    participant H as view() helper
    participant E as View engine
    participant Child as Child template
    participant Lay as Layout
    participant Part as partials/alerts
    participant Resp as Response

    C->>H: view('app.workspaces.select', {workspaces, title})
    H->>E: render('app.workspaces.select', data)
    E->>E: snapshot+reset sections; merge shared globals
    E->>Child: include child (ob_start)
    Child->>E: $this->extends('layouts.app')
    Child->>E: section('content') ... endSection()
    E->>Lay: include layout (ob_start)
    Lay->>E: read $user, $workspace; build $nav; filter by can()
    Lay->>Part: $this->include('partials.alerts')
    Part-->>Lay: flash HTML
    Lay->>E: $this->yield('content')
    E-->>H: composed HTML string
    H-->>C: Response (200, HTML)
    C->>Resp: return Response
```

### Locale resolution + direction

```mermaid
sequenceDiagram
    participant U as User
    participant K as Kernel (resolveLocale)
    participant S as Session
    participant T as Translator
    participant L as Layout
    U->>K: GET /dashboard?lang=ar
    K->>K: ?lang in supported_locales?
    K->>S: session put 'locale' = 'ar'
    K->>T: setLocale('ar')
    Note over K,T: On later requests with no ?lang,<br/>kernel reuses session locale,<br/>then falls back to the user's profile locale
    L->>T: locale() / is_rtl()
    T-->>L: 'ar' / true
    L->>U: <html lang="ar" dir="rtl"> …
```

## Business Rules

1. **Server renders everything.** Every page is a complete HTML document from a PHP template; JS is never required to render core content.
2. **No server-side build.** The compiled `public/assets/css/app.css` and `public/assets/js/app.js` ship in the repo; nothing is compiled during install or at runtime.
3. **Two layouts, one shell.** Public/auth/installer use `layouts/guest.php`; the authenticated app uses `layouts/app.php`. New pages extend the appropriate layout.
4. **No dead links.** The nav registry renders a link only when the module is `built` and the user `can()` see it.
5. **All output is escaped.** Dynamic values pass through `e()` in templates; raw echoing of user data is forbidden.
6. **Direction comes from the root element.** Never hard-code `left`/`right`; use flow-relative utilities so RTL/LTR are automatic.
7. **Translatable copy uses `__()`** and lives in `resources/lang/{en,ar}/` — no hard-coded user-facing strings that need translation.
8. **CSRF on every form.** Each `<form>` that mutates state includes `csrf_field()`; non-GET methods use `method_field()` for spoofing (`PUT`/`PATCH`/`DELETE`).
9. **Assets are versioned**, always referenced through `asset()` so cache-busting is consistent.

## Database Relations

The frontend has no direct DB access — it renders data handed to it by controllers/services (the model layer enforces tenancy upstream). The few things templates read are already-resolved objects:

- The **authenticated layout** reads `auth()->user()` (from `users`) and `tenant()->workspace()` (the active `workspaces` row) for the topbar, and `$user->workspaces()` (via `memberships`) to render the workspace switcher.
- The **nav registry** is gated by `can($permission)`, which resolves against `roles`/`permissions`/`role_permissions`/`membership_roles`/`user_roles` in `AccessControl` — but the template only ever sees the boolean. See [05 — Database Architecture](05-Database-Architecture.md) and [07 — RBAC](07-RBAC.md).

## Permissions

- **Navigation visibility** is permission-driven: `dashboard.view`, `members.view`, `roles.view`, `ai.view`, `billing.view`, `settings.view` gate their sidebar entries via `can()`.
- **In-page controls** should likewise be wrapped in `can()` so users never see buttons they cannot use (e.g. a "Create job" button only for `jobs.create`).
- The frontend reflects authorization but does **not** enforce it — every action is independently checked server-side by middleware/services (defense in depth). See [11 — Permissions Matrix](11-Permissions-Matrix.md).

## Validation

- **Display side:** validation errors flashed by the backend are surfaced by `partials/alerts.php` and re-populated into form fields via `old('field')`, so users keep their input after a failed submit.
- **Inputs** use native HTML constraints (`required`, `type="email"`, `minlength`) as a first, non-authoritative pass; the authoritative validation is server-side (`Controller::validate()`), so JS-disabled or crafted requests are still rejected.
- **No client-only validation** is trusted; the UI's role is feedback, not enforcement. See [31 — Backend Architecture](31-Backend-Architecture.md).

## Edge Cases

| Case | Handling |
|---|---|
| JavaScript disabled | All flows work; dropdowns are pure-HTML `<details>` and still open/close; only the click-outside-to-close nicety is lost. |
| Missing template | `View::resolve()` throws a clear `RuntimeException("View [x] not found …")`. |
| Arabic content in a mostly-LTR layout | Root `dir="rtl"` plus flow-relative utilities mirror the whole shell; no per-element overrides needed. |
| Long workspace/user names | Topbar uses `truncate`/`min-w-0` so layout doesn't break. |
| Flash with no messages | `partials/alerts.php` renders nothing. |
| Stale CSS after a release | Bump `ASSET_VERSION`; the `?v=` string forces a refetch. |
| No active workspace (chooser/landing) | Layout falls back to showing the app name instead of the switcher. |
| `?lang=` with an unsupported value | Ignored (`resolveLocale()` checks `supported_locales`); current locale stands. |
| Error pages | Rendered from `resources/views/errors/*` (403/404/419/500/generic) using the same escaping, independent of the app shell. |

## Security

- **Output escaping** via `e()` (`htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8) on all dynamic values is the primary XSS defense.
- **CSRF tokens** in every state-changing form (`csrf_field()`), plus a `<meta name="csrf-token">` for any future fetch/AJAX writes; verified by `VerifyCsrfToken`.
- **Security headers** applied to every response (`SecurityHeaders`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` (camera/mic/geolocation denied), HSTS over HTTPS — these constrain what the frontend can do and how it can be embedded.
- **No inline JS / no third-party CDNs for scripts** — all JS is the local, reviewed `app.js`, keeping the script surface first-party.
- **Assets are local** (`public/assets`), so there is no external dependency that could be tampered with at runtime. See [34 — Security](34-Security.md).

## Performance

- **Single compiled CSS** and a tiny `defer`-loaded JS — two static assets, served by the web server, cached via `?v=`.
- **No client framework / no hydration cost** — the browser parses ready HTML.
- **Compile-free templates** mean no template-cache warmup; OPcache keeps the included PHP hot.
- **Server-side pagination** (`QueryBuilder::paginate()`) keeps list pages small; templates render a page at a time, not full datasets.
- **`preconnect`** hints (e.g. fonts) where used reduce connection latency. See [35 — Performance](35-Performance.md).

## Testing

- **Design System tests:** `tests/Feature/ComponentTest.php` renders each component and asserts its contract — classes, ARIA roles (`dialog`/`tablist`/`switch`/`progressbar`/`alert`), data-hooks (`data-modal-open`, `data-tab-target`), escaping, and the `/design` catalog rendering 200 through the app shell with the dark-mode toggle, no-flash script and toast region present.
- **Rendering unit tests:** `View` layout inheritance (child `content` flows into layout), explicit vs implicit `content` section, nested `include` isolation, `yield` defaults, missing-view error.
- **Bilingual tests:** rendering with locale `ar` yields `<html lang="ar" dir="rtl">` and `en` yields `dir="ltr"`; `?lang=ar` persists across requests; flow-relative classes present (no raw `left:`/`right:`).
- **Permission-visibility tests:** sidebar shows only links the user `can()` see and that are `built`; unauthorized/unbuilt links absent.
- **Form tests:** every mutating form contains `_token`; `PUT`/`DELETE` forms contain `_method`; `old()` repopulates after a validation failure.
- **Accessibility checks (QA):** labels/`for` associations, focus order, keyboard operability of `<details>` menus, color-contrast on `brand`/`slate` palette, `Escape` closes menus.
- **No-JS smoke test:** core flows (login, create workspace, switch workspace, logout) succeed with scripting disabled.
- See [39 — Testing Strategy](39-Testing-Strategy.md) and [40 — QA Checklist](40-QA-Checklist.md).

## Future Expansion

- **Component partials library** — ✅ *shipped*: 29 parameterised components in `resources/views/components/` rendered via `component()`, documented live at `/design`. Future growth adds more primitives (DatePicker, Autocomplete, Calendar, DataGrid) to the same library, never bespoke per-page UI.
- **Optional richer interactivity**: drop in a tiny, no-build library (e.g. Alpine.js via a local file) for client state on heavy screens (kanban application pipeline, live AI interview) — still no bundler, still progressive.
- **API-backed widgets**: dashboards can fetch JSON from the planned `/api/v1` ([29](29-API-Architecture.md)) using `fetch` + the `<meta name="csrf-token">`, keeping the page server-rendered with islands of dynamism.
- **Theming per tenant**: workspace-level branding (logo, brand color) driven by `workspaces.settings`, applied as CSS variables in the layout.
- **Asset fingerprinting**: move from `?v=` query strings to content-hashed filenames if/when an offline build step is introduced for the design team (still shipped pre-built).
- **More locales**: the `Translator` + `resources/lang/<code>/` + root-element direction model extends to any additional language/RTL script with no template changes.

## Open Questions

None at this time. Whether to introduce a minimal no-build JS helper library for highly-interactive screens (kanban, live interview) is tracked in [45 — Future Roadmap](45-Future-Roadmap.md).
