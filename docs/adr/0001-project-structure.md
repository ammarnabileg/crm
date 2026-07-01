# ADR 0001 — Project Structure

> **Status:** Accepted · **Date:** 2026-06-27 · **Deciders:** Architecture
> **Supersedes:** the top-level layout originally drafted in
> `PROJECT_CONSTITUTION.md` §8 (v1.0.0).

## Context

Phase 1 (`PROJECT_CONSTITUTION.md` §8, `ARCHITECTURE.md` §3) drafted a top-level
layout with modules at `/modules` and the shared kernel at `/shared`. Phase 6
("Project Structure, Build System & Zero-Touch Installer") explicitly proposed a
different top-level layout:

```
/app/{Core, Modules, Shared, Infrastructure, Services, Providers, Contracts, Support}
/bootstrap  /config  /database  /public  /resources  /storage  /routes  /docs  /tests  /vendor
```

This is a genuine conflict between two adopted documents. Per
`PROJECT_CONSTITUTION.md` §16, a real conflict is reconciled by amendment.

## Decision

1. **Adopt the Phase 6 top-level structure as canonical**, documented
   authoritatively in `PROJECT_STRUCTURE.md`. Modules live under `app/Modules`,
   the Shared Kernel under `app/Shared`, the Core Kernel under `app/Core`, with
   `app/{Infrastructure,Services,Providers,Contracts,Support}` for app-level
   cross-cutting code, plus root `bootstrap/`, `routes/`, etc.
2. **Keep the DDD-layered module internals** (`Domain/Application/Infrastructure/
   Presentation/Contracts` + supporting dirs) from `ARCHITECTURE.md` §3 — these
   are unchanged; only the parent path becomes `app/Modules/<Module>/`.
3. **Map conventional artifact names to layers** (Controllers→Presentation,
   Services→Application, Repositories→Infrastructure, Views/Assets→Resources) so
   Phase 6's enumerated module artifacts and Phase 1's DDD layers agree.
4. **Amend Phase 1 docs** (`PROJECT_CONSTITUTION.md` §8, `ARCHITECTURE.md` §3) to
   reference the new layout.

## Consequences

- A single authoritative structure (`PROJECT_STRUCTURE.md`) the code phases
  (7–8) implement directly.
- No change to the modular-monolith principles, dependency rules, or per-module
  layering — only paths.
- Namespaces become `HaHireAI\Core\…`, `HaHireAI\Shared\…`,
  `HaHireAI\Modules\<Module>\<Layer>\…`.
- Only `public/` remains web-exposed (unchanged).
