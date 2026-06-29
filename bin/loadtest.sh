#!/usr/bin/env bash
# Phase 16 — minimal throughput baseline for a single endpoint.
#
#   bin/loadtest.sh [URL] [REQUESTS] [CONCURRENCY]
#
# Note: the PHP built-in server is single-threaded; this is a smoke-level baseline.
# Production runs PHP-FPM + nginx with real concurrency. See docs/RELEASE_CERTIFICATION.md.
set -euo pipefail

URL="${1:-http://127.0.0.1:8062/api/v1/ping}"
N="${2:-300}"
C="${3:-10}"

echo "Load test: ${N} requests, concurrency ${C} → ${URL}"
start=$(date +%s.%N)

seq "$N" | xargs -P "$C" -I{} curl -s -o /dev/null -w '%{http_code}\n' "$URL" \
  | sort | uniq -c | sed 's/^/  /'

end=$(date +%s.%N)
elapsed=$(echo "$end - $start" | bc)
rps=$(echo "scale=1; $N / $elapsed" | bc)
echo "Elapsed: ${elapsed}s   Throughput: ~${rps} req/s"
