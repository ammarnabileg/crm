# DIRECTORY STANDARD — HaHireAI

> **Status:** Adopted (Canon) · **Version:** 1.0.0 · **Last updated:** 2026-06-27
> **Defers to:** `PROJECT_STRUCTURE.md`, `PROJECT_CONSTITUTION.md` §7–§9.

Naming and placement rules for every folder, file, and namespace. No random or
ad-hoc names are permitted.

---

## 1. Namespaces mirror paths (PSR-4)

| Path | Namespace root |
|---|---|
| `app/Core/…` | `HaHireAI\Core\…` |
| `app/Shared/…` | `HaHireAI\Shared\…` |
| `app/Infrastructure/…` | `HaHireAI\Infrastructure\…` |
| `app/Services/…` | `HaHireAI\Services\…` |
| `app/Providers/…` | `HaHireAI\Providers\…` |
| `app/Contracts/…` | `HaHireAI\Contracts\…` |
| `app/Support/…` | `HaHireAI\Support\…` |
| `app/Modules/<Module>/<Layer>/…` | `HaHireAI\Modules\<Module>\<Layer>\…` |

Composer `autoload.psr-4` maps `HaHireAI\\` → `app/`. One class per file; the
file name equals the class name.

## 2. Folder naming

- Top-level and `app/*` folders: **PascalCase** for namespaced code roots
  (`Core`, `Modules`, `Shared`, `Infrastructure`), **lowercase** for
  non-namespaced operational roots (`bootstrap`, `config`, `database`, `public`,
  `resources`, `routes`, `storage`, `tests`, `docs`, `vendor`).
- Module folders: **PascalCase** module name (`Recruitment`, `Workspaces`).
- Module layer folders: fixed set, PascalCase (`Domain`, `Application`,
  `Infrastructure`, `Presentation`, `Contracts`, `Resources`, `Routes`, `Config`,
  `Permissions`, `Policies`, `Events`, `Database`, `Tests`).
- Inside `Resources/`: lowercase `views/`, `assets/`.

## 3. File naming

| File type | Convention | Example |
|---|---|---|
| PHP class/interface/enum | PascalCase, one per file | `JobService.php`, `JobRepository.php` |
| Interface (public contract) | PascalCase in `Contracts/`, no suffix | `Contracts/JobService.php` |
| Concrete implementation | technology/role-prefixed | `MySqlJobRepository.php` |
| Config file | kebab-case `.php` returning array | `config/database.php` |
| Route file | kebab/lowercase `.php` | `app/Modules/Jobs/Routes/web.php` |
| Migration | timestamp/sequence + snake description | `2026_06_27_000001_create_jobs.php` |
| View/partial | kebab-case `.php` | `views/job-detail.php` |
| JS/CSS source | kebab-case | `resources/js/sidebar.js` |
| Lang file | locale dir + domain | `resources/lang/ar/recruitment.php` |
| Test | mirrors subject + `Test` | `Tests/Unit/JobServiceTest.php` |
| Doc | `UPPER_SNAKE.md` (specs: `Pascal_Module.md`) | `DATABASE_GUIDE.md`, `FEATURE_SPECIFICATIONS/Recruitment.md` |
| ADR | `NNNN-kebab-title.md` | `adr/0001-project-structure.md` |

## 4. Placement rules

- A class goes in the layer that matches its role (`PROJECT_STRUCTURE.md` §4.1).
- A module's public API lives **only** in its `Contracts/`.
- Shared, reusable primitives go in `app/Shared`; technical drivers in
  `app/Infrastructure`; stateless helpers in `app/Support`.
- Anything workspace-scoped persists with `workspace_id` (`DATABASE_GUIDE.md`).
- Tests live with their module (`<Module>/Tests/`); only cross-module/e2e tests
  live in the root `tests/`.

## 5. Prohibited

- Generic dumping grounds (`misc/`, `helpers/` inside modules, `utils.php`).
- Two classes in one file; file name ≠ class name.
- Business logic in `app/Core`, `app/Shared`, `app/Support`, `bootstrap/`.
- A module folder anywhere other than `app/Modules/`.

---

### Related Documents
`PROJECT_STRUCTURE.md` · `PROJECT_CONSTITUTION.md` · `ARCHITECTURE.md` ·
`CODING_STANDARD.md`
