# CLAUDE.md

Guidance for Claude Code (and humans) working in this repository.

## Project

HaHireAI — an AI-native, multi-tenant hiring-operations platform written in
**native PHP 8.3+** as a modular monolith. No framework, and **zero Composer
runtime dependencies**: `bootstrap/autoload.php` ships a PSR-4 fallback so the
product is upload-and-run on shared hosts. Namespace root `HaHireAI\` maps to
`app/`. Business logic uses dependency injection (no service locator).

## Commands

```bash
composer install          # dev tools only (phpunit, phpstan) — no runtime deps
vendor/bin/phpunit --testsuite unit    # fast, no database
vendor/bin/phpunit                     # full suite — Feature tests need MySQL
composer analyse          # PHPStan (level 4)
composer smoke            # php bin/smoke.php
```

## ⚠️ Running in constrained containers (Claude Code on the web) — READ THIS

**Symptom (historical):** the interactive container becomes “fork-dead” — even
`echo` fails with a non-zero exit — and the Claude session dies. It happened
repeatedly.

**Root cause:** the only subprocess spawn in the whole test suite is
`tests/Feature/VendorlessBootTest.php`, which runs `exec('php …')`. When that
runs together with an asset build (`npx tailwindcss`) and the full test suite
(plus MySQL) inside a small container, the process table fills, `exec` fails
with **“Unable to fork”**, and the failure cascades until the shell can no
longer spawn anything — taking the session down.

**Permanent guardrails (already in place — keep them):**

1. `.claude/settings.json` sets **`HAHIREAI_SKIP_SUBPROCESS_TESTS=1`** for every
   Claude session here. With it set, `VendorlessBootTest` skips **before** any
   `exec()` — so the entire suite runs with **zero forks** and cannot fork-bomb
   the container. CI does **not** read `.claude/settings.json`, so the vendorless
   guarantee is still fully enforced there (the flag is unset on CI).
2. A `SessionStart` hook (`.claude/hooks/session-start.sh`) prints these rules
   into each new session.

**Operating rules for any session in this repo:**

- Do **not** run `npx` / `bin/build-assets.sh` (asset build) together with the
  full test suite in the interactive container. Asset builds belong to **CI /
  deploy**, which run on a clean box with room to fork.
- Prefer `vendor/bin/phpunit --testsuite unit`, or run Feature tests in small
  groups. The **full** Feature suite (needs MySQL) and **PHPStan** are CI jobs.
- If a shell command starts failing with non-zero exits for no reason, the
  container is likely fork-exhausted or being recycled. **Commit/push work via
  the GitHub API immediately** rather than fighting the shell, then continue in
  a fresh session.

## Architecture notes

- Entry point: `index.php` → `app/Core/Kernel.php` (boot → handle request).
- Config in `config/*.php`, read via `config()`; env via `.env` (`env()`), never
  hardcode secrets.
- Sessions: `config/session.php` + `app/Core/Http/Session/*` — driver-based
  storage, HTTPS-aware Secure cookie (see docs/SECURITY_GUIDE.md).
- Every query uses prepared statements (`app/Core/Database/Connection.php`); no
  string-concatenated SQL.
