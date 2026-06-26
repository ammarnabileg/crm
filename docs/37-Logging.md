# 37 — Logging (التسجيل)

The logging architecture of HalaOps: leveled, newline-delimited file logs written by `App\Core\Logger` to `storage/logs`, with request and error logging, structured JSON context, correlation ids, daily rotation and retention, and a strict rule that secrets and PII never reach a log line — distinct from, and complementary to, the business audit trail.

## Related Documents

- [33 — System Diagnostics](33-System-Diagnostics.md)
- [34 — Security](34-Security.md)
- [35 — Performance](35-Performance.md)
- [38 — Audit System](38-Audit-System.md)
- [43 — Deployment](43-Deployment.md)
- [44 — Production Checklist](44-Production-Checklist.md)

---

## Purpose (الهدف)

This document defines **how HalaOps records operational events** for debugging, monitoring, and incident response. It specifies the logger (`App\Core\Logger`), the log levels and their meaning, what context each entry carries, how requests and errors are logged, correlation ids that tie a user action to its log lines, rotation and retention, and — crucially — **what must never be logged**. It draws a clear line between *logging* (operational, transient, for engineers) and *auditing* (business/security record of who did what, for compliance — see [38 — Audit System](38-Audit-System.md)).

## Why It Exists (سبب وجوده)

On the buyer's typical deployment there is **no external log aggregator, no APM, and no shell** to tail processes interactively. When something breaks, the file logs under `storage/logs` are often the only forensic evidence available, surfaced to super admins through the diagnostics screen. So logging must be:

- **Self-contained** — pure PHP, file-based, zero dependencies (consistent with the no-framework principle).
- **Safe** — a recruitment platform's logs could otherwise become a secondary leak of candidate PII or tenant AI keys; the logger must be impossible to misuse into recording secrets.
- **Useful** — leveled and structured enough to diagnose a problem from a single day's file, with a correlation id to follow one request end to end.
- **Bounded** — rotated daily and pruned by retention so logs never fill a small host's disk.

## Architecture

```mermaid
flowchart TD
    A[Request lifecycle / Application kernel] --> B[logger() -> App\Core\Logger]
    B --> C{level}
    C -->|emergency/error/warning| D[storage/logs/app-YYYY-MM-DD.log]
    C -->|info/debug| D
    D --> E[Daily rotation by filename date]
    E --> F[Retention pruning by age]
    D --> G[Diagnostics viewer super admin]
    H[Audit events] -. separate path .-> I[(activity_log table)]
```

`App\Core\Logger` is a minimal PSR-3-style file logger resolved from the container as `log` (helper `logger()`):

- **Levels** (methods): `emergency`, `error`, `warning`, `info`, `debug`, plus the generic `log($level, …)`.
- **Destination**: `storage/logs/app-YYYY-MM-DD.log` — the date in the filename *is* the daily rotation mechanism; a new file appears each day automatically.
- **Format**: `[Y-m-d H:i:s] LEVEL: message {json-context}` — one line per entry, context JSON-encoded with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` so Arabic text and URLs stay readable.
- **Resilience**: it creates the log directory if missing and **silently no-ops if the directory is not writable** (`@file_put_contents(..., FILE_APPEND | LOCK_EX)`), so logging can never crash a request — a deliberate availability trade-off.
- **Concurrency**: `LOCK_EX` on append keeps lines from interleaving under concurrent writes on a single node.

Relationship to other systems: the application kernel (`App\Core\Application`) uses the logger for central error handling; diagnostics ([33 — System Diagnostics](33-System-Diagnostics.md)) reads/exposes the files; the audit system ([38 — Audit System](38-Audit-System.md)) is a *different* sink (the database) for a different purpose.

## Workflow

### Logging an error during a request

```mermaid
sequenceDiagram
    participant R as Request
    participant K as Application kernel
    participant L as Logger
    participant F as app-YYYY-MM-DD.log
    R->>K: request enters, correlation id assigned
    K->>K: controller/service throws Throwable
    K->>L: logger()->error(message, {request_id, method, path, status, exception_class})
    L->>F: append "[ts] ERROR: message {json}"
    K-->>R: render error view (generic; debug page only if APP_DEBUG)
```

### Levels and when to use them

| Level | Use for | Example |
| --- | --- | --- |
| emergency | App unusable | cannot connect to DB at boot |
| error | A request/operation failed | unhandled exception, failed external AI call after retries |
| warning | Recoverable / suspicious | deprecated path hit, repeated auth failures, replica fallback |
| info | Notable lifecycle events | worker started, migration ran, scheduled task completed |
| debug | Developer detail (non-prod) | query timings, branch decisions — disabled in production |

## Business Rules

1. **Never log secrets or credentials.** Passwords, password-reset tokens, API tokens, session ids, CSRF tokens, and **AI provider keys** must never appear in a log line or context array.
2. **Never log full PII payloads.** Candidate resumes, full contact details, interview transcripts, and message bodies are not logged; reference them by id instead (e.g. `application_id`, `user_id`).
3. **Log levels have defined meaning** (table above); production runs at `info` and above — `debug` is for non-production only.
4. **Every error is logged with enough context to reproduce** (correlation id, method, path, status, exception class/message) — but not the raw request body.
5. **Context is structured**, passed as the `array $context` argument and JSON-encoded — never string-concatenated into the message.
6. **A correlation id ties all lines of one request together** and is included in error responses' context so a user-reported issue can be located.
7. **Logging must never break a request.** The logger no-ops on an unwritable directory rather than throwing.
8. **Logs are rotated daily and retained for a bounded window** (default 14–30 days), then pruned.
9. **`storage/logs` is never web-accessible** (outside the document root, denied by `.htaccess`).
10. **Operational logging is separate from auditing.** Compliance-relevant "who did what" goes to `activity_log` ([38 — Audit System](38-Audit-System.md)), not to the file log.

## Database Relations

Logging is **file-based and intentionally has no table** — it must work before/around the database and must not add DB load on the hot path. Related data lives elsewhere:

- **activity_log** — the *audit* sink (a real table per §11); the file log and the audit table are deliberately distinct (see [38 — Audit System](38-Audit-System.md)).
- **failed_jobs** — background-job failures are persisted here for retry/inspection in addition to being logged.
- **gateway_events** — payment webhook payloads are stored for reconciliation; the file log records processing outcomes (success/failure) without dumping full payloads.

If a database/searchable log store is ever required, it is added behind the same `Logger` seam (see Future Expansion); the default remains file-based for zero-dependency hosting.

## Permissions

- `platform.diagnostics` — only super admins may view operational logs through the diagnostics screen ([33 — System Diagnostics](33-System-Diagnostics.md)); logs may contain cross-tenant operational detail, so they are platform-scoped, not tenant-visible.
- Tenants and ordinary users have **no access** to raw operational logs; their visibility into "what happened" comes from in-app notifications and the audit trail surfaced within their tenant.

## Validation

- **Context arrays are sanitised before writing**: known sensitive keys (`password`, `token`, `api_key`, `secret`, `authorization`, `credentials`, `_token`) are stripped/redacted; values are coerced to scalars/short strings to avoid dumping large objects.
- **Message length is bounded** and user-supplied strings within messages are treated as data, not format strings (no untrusted input passed as a `sprintf` format).
- **Log level is validated** against the known set; unknown levels fall back to the generic `log()` path.
- **User-agent and similar free-text** are truncated (mirroring the 255-char cap used when auditing) before inclusion.

## Edge Cases

- **Unwritable `storage/logs`** (bad permissions on shared hosting) → logger silently no-ops; diagnostics' "storage writable" health check surfaces the misconfiguration so it is fixed.
- **Disk full** → appends fail silently; retention pruning and rotation are designed to prevent this; diagnostics flags low disk where available.
- **High-volume error storm** → daily files can grow large; severity discipline (don't log expected validation failures as errors) and retention keep size bounded; rate-limited logging can be added if a single error floods.
- **Concurrent writes across multiple app nodes** → file `LOCK_EX` only orders writes within one node; at horizontal scale, logs are shipped/centralised (see [36 — Scalability](36-Scalability.md), Future Expansion).
- **Sensitive data accidentally placed in a message** → caught by review and by the context redaction rule; the standing rule is "reference by id, never by content".
- **Clock skew** between nodes → timestamps are local server time; correlation ids (not timestamps) are the primary join key for a request.

## Performance

- **Append-only writes** are cheap; the logger does a single `file_put_contents(FILE_APPEND | LOCK_EX)` per entry.
- **Production runs at `info`+**, so `debug` calls add no IO in production.
- **No DB round-trip** for operational logging keeps the hot path free of extra queries (contrast with the audit insert, which is intentional and indexed — see [35 — Performance](35-Performance.md)).
- **Context is encoded once** with fast flags; avoid logging huge arrays/objects (a rule, enforced by sanitisation and truncation).
- **Rotation by filename** means no expensive log-file scanning/rewriting at runtime; pruning is a periodic background task, not a per-request cost.

## Testing

(See [39 — Testing Strategy](39-Testing-Strategy.md).)

- **Redaction tests (security):** logging a context containing `password`/`token`/`api_key`/`credentials` writes a redacted value; the secret never appears in the file.
- **PII tests:** error logging for an application/interview records ids, not transcript/resume content.
- **Format tests:** entries match `[timestamp] LEVEL: message {json}`; Arabic context is not escaped into `\uXXXX` (uses `JSON_UNESCAPED_UNICODE`).
- **Rotation test:** entries on different dates land in different `app-YYYY-MM-DD.log` files.
- **Resilience test:** with an unwritable log directory, `Logger::log()` does not throw and the request completes.
- **Correlation id test:** all log lines for a simulated request share the same id; an error response carries that id.
- **Level filtering test:** in production config, `debug()` produces no output while `error()` does.
- **Retention test:** the pruning task removes files older than the configured window and keeps newer ones.

## Future Expansion

- **Structured JSON-per-line output mode** (full JSON objects) for ingestion by Loki/ELK/Datadog when an aggregator is available.
- **Pluggable log handlers** behind the `Logger` interface: syslog, stderr (for containerised/12-factor deployments), or a remote shipper — selected by config without changing call sites.
- **Centralised log shipping** at horizontal scale so multi-node logs are searchable in one place (see [36 — Scalability](36-Scalability.md)).
- **Automatic correlation/trace propagation** into queued jobs and outbound AI/gateway calls for end-to-end tracing.
- **Configurable retention and rotation** (size-based in addition to daily) surfaced in admin settings.
- **Alerting hooks** on `emergency`/`error` rate thresholds, integrated with diagnostics and notifications.
- **Sampling for `debug`/high-volume paths** to retain signal without flooding storage.

## Open Questions

None at this time. The logging mechanism, levels, safety rules, and retention are fully defined by `App\Core\Logger` and the canonical context (§13); richer sinks and aggregation are deferred to Future Expansion and remain behind the existing logger seam.
