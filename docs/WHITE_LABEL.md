# White Label & Branding

Per-company visual identity, applied across the authenticated app shell and the
candidate-facing surfaces. White Label is a **paid plan feature** (`white_label`)
in the workspace wallet model (see `WALLET_AND_BILLING.md`).

## One source of truth

All brand data lives as `brand.*` rows in `workspace_settings` (no schema
change). The single owner of the brand definition is:

`app/Modules/Workspaces/Application/BrandingService.php`

Every consumer goes through it — there is no second place that defines brand
fields or reads brand keys directly:

| Consumer | Uses |
|---|---|
| Branding Center (edit form) | `fields()`, `values()`, `save()` |
| `WorkspaceShell` (every authenticated page) | `shellStyle()`, `logoUrl()` |

`BrandingService` owns:

- **`fields()`** — the field schema (key → label, input type, group, options,
  help). The Branding Center renders straight from this, so adding a field is a
  one-line change in one file.
- **`values($ws)` / `save($ws, $input)`** — read/write every field as a
  `brand.<key>` preference.
- **`shellStyle($ws)`** — the inline `style` applied to `#app-shell`: the full
  `--brand-*` colour scale (the app's Tailwind maps every `indigo-*` utility
  onto these channels via `BrandPalette`) plus a direct `font-family` so the
  chosen typography cascades to the whole subtree.
- **`logoUrl($ws)`** — the workspace logo asset URL (uploaded via Settings).

## Fields

| Group | Field | Type | Applied |
|---|---|---|---|
| Identity | `company_name`, `legal_name`, `description` | text / textarea | careers page, profile |
| Colours | `color` (primary), `secondary_color`, `accent_color` | colour | `color` drives the whole app accent scale |
| Style | `font_family` | select | app-wide `font-family` on `#app-shell` |
| Style | `radius` | number | captured (corner-radius preference) |
| Career page | `career_hero_title`, `career_hero_subtitle` | text | public careers page |
| Footer & social | `footer_text`, `social_website`, `social_linkedin`, `social_twitter` | text / url | careers page footer |

Typography choices map to system-safe CSS font stacks (plus the layout's bundled
Inter), so no external font fetch is required: `system`, `inter`, `rounded`
(Trebuchet), `serif` (Georgia), `mono`.

## Entitlement gating

`WorkspaceShell::brand()` only applies the custom colour scale, font and logo
when the plan includes `white_label` (or there is no composed plan yet — the
dev/unlimited case). Without the feature the shell keeps the company **name**
but renders the default HaHireAI theme. Saving in the Branding Center is gated
the same way (plus the `workspace.branding` permission), so a workspace cannot
edit branding it has not paid for.

## Backward compatibility

No tables, routes, permissions or the `brand` view-model shape
(`{name, initial, logoUrl, style}`) changed. Existing `brand.color` /
`brand.logo_file_id` preferences are read unchanged; the only behavioural change
is that the custom theme is now correctly withheld from workspaces that do not
hold the `white_label` feature.
