#!/usr/bin/env bash
# HaHireAI — SessionStart hook for Claude Code (web / constrained containers).
#
# Purpose: surface the guardrails that stop fork-exhaustion from killing the
# session. The actual protection is the HAHIREAI_SKIP_SUBPROCESS_TESTS=1 env var
# set in .claude/settings.json; this hook just makes each new session aware.
# It performs NO fork-heavy work itself and never blocks startup.
set -u

echo 'HaHireAI session ready. Guardrails against fork-exhaustion crashes:'
echo '- HAHIREAI_SKIP_SUBPROCESS_TESTS=1 is set, so the vendorless subprocess test skips (no fork).'
echo '- Prefer: vendor/bin/phpunit --testsuite unit   (or run tests in small groups).'
echo '- The full Feature suite needs MySQL and the PHPStan analysis are CI jobs; run them there.'
echo '- Do NOT run npx / bin/build-assets.sh together with the full test suite in this container.'
exit 0
