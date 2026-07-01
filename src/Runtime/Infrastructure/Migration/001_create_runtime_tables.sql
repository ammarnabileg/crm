-- ============================================================================================
-- Runtime execution persistence & event-sourced state store (Postgres 16).
-- Realizes ADR-0021 (implements ADR-0018 execution state machine; does not change it).
--
-- Design notes:
--   * Every table is tenant-scoped (tenant_id) and carries audit columns; the aggregate snapshot
--     and its child projections are soft-deletable (deleted_at), while execution_history is a
--     strictly append-only audit log and is never soft-deleted or mutated.
--   * UUIDv7 primary keys are minted by the application (Nizam\Platform\Support\Uuid::v7); no DB
--     default is declared so the app and DB agree on the id scheme across both drivers.
--   * JSON documents use JSONB. execution_history.payload is the authoritative event stream from
--     which an Execution aggregate is replayed; the other JSON columns are queryable projections.
--   * Indexes serve the hot read paths: (tenant_id, state) to list/monitor a tenant's executions,
--     (execution_id, sequence_no) to replay one execution's history in order.
-- ============================================================================================

-- executions -------------------------------------------------------------------------------
-- The current, queryable snapshot of each execution aggregate. Authoritative behaviour is
-- rebuilt from execution_history; this row exists so callers can filter and monitor cheaply.
CREATE TABLE IF NOT EXISTS executions (
    id              UUID        NOT NULL,
    tenant_id       UUID        NOT NULL,
    user_id         UUID        NULL,
    department_ref  TEXT        NULL,
    manager_ref     TEXT        NULL,
    intent_ref      TEXT        NOT NULL,
    state           TEXT        NOT NULL,
    metadata        JSONB       NOT NULL DEFAULT '{}'::jsonb,
    cost            JSONB       NOT NULL DEFAULT '{}'::jsonb,
    performance     JSONB       NOT NULL DEFAULT '{}'::jsonb,
    timeline        JSONB       NOT NULL DEFAULT '[]'::jsonb,
    attempts        INTEGER     NOT NULL DEFAULT 1,
    created_at      TIMESTAMPTZ NOT NULL,
    updated_at      TIMESTAMPTZ NOT NULL,
    deleted_at      TIMESTAMPTZ NULL,
    version         INTEGER     NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CHECK (attempts >= 1),
    CHECK (version >= 0)
);
CREATE INDEX IF NOT EXISTS idx_executions_tenant_state
    ON executions (tenant_id, state)
    WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_executions_tenant_created
    ON executions (tenant_id, created_at DESC)
    WHERE deleted_at IS NULL;

-- execution_history ------------------------------------------------------------------------
-- The append-only event store. One row per recorded domain event, ordered per execution by
-- sequence_no. Never updated, never deleted: this is the audit-grade source of truth.
CREATE TABLE IF NOT EXISTS execution_history (
    id            UUID        NOT NULL,
    tenant_id     UUID        NOT NULL,
    execution_id  UUID        NOT NULL,
    sequence_no   INTEGER     NOT NULL,
    event_name    TEXT        NOT NULL,
    payload       JSONB       NOT NULL DEFAULT '{}'::jsonb,
    occurred_at   TIMESTAMPTZ NOT NULL,
    recorded_at   TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (id),
    UNIQUE (execution_id, sequence_no),
    CHECK (sequence_no >= 0)
);
CREATE INDEX IF NOT EXISTS idx_execution_history_execution
    ON execution_history (execution_id, sequence_no);
CREATE INDEX IF NOT EXISTS idx_execution_history_tenant
    ON execution_history (tenant_id, occurred_at);

-- execution_locks --------------------------------------------------------------------------
-- Advisory lock rows serializing work on a single execution across processes. One row per lock
-- key; expires_at implements the TTL so a crashed holder cannot block a key forever.
CREATE TABLE IF NOT EXISTS execution_locks (
    lock_key    TEXT        NOT NULL,
    owner_token UUID        NOT NULL,
    acquired_at TIMESTAMPTZ NOT NULL,
    ttl_ms      INTEGER     NOT NULL,
    expires_at  TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (lock_key),
    CHECK (ttl_ms >= 1)
);
CREATE INDEX IF NOT EXISTS idx_execution_locks_expires
    ON execution_locks (expires_at);

-- execution_timeline -----------------------------------------------------------------------
-- The human-readable, ordered path an execution took, denormalized for the Execution Monitor.
CREATE TABLE IF NOT EXISTS execution_timeline (
    id           UUID        NOT NULL,
    tenant_id    UUID        NOT NULL,
    execution_id UUID        NOT NULL,
    sequence_no  INTEGER     NOT NULL,
    state        TEXT        NOT NULL,
    note         TEXT        NOT NULL,
    entered_at   TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ NOT NULL,
    deleted_at   TIMESTAMPTZ NULL,
    PRIMARY KEY (id),
    UNIQUE (execution_id, sequence_no),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_execution_timeline_execution
    ON execution_timeline (tenant_id, execution_id, sequence_no)
    WHERE deleted_at IS NULL;

-- execution_logs ---------------------------------------------------------------------------
-- Free-form, friendly log lines surfaced on the Execution Monitor (no stack traces).
CREATE TABLE IF NOT EXISTS execution_logs (
    id           UUID        NOT NULL,
    tenant_id    UUID        NOT NULL,
    execution_id UUID        NOT NULL,
    level        TEXT        NOT NULL DEFAULT 'info',
    message      TEXT        NOT NULL,
    logged_at    TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ NOT NULL,
    deleted_at   TIMESTAMPTZ NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_execution_logs_execution
    ON execution_logs (tenant_id, execution_id, logged_at)
    WHERE deleted_at IS NULL;

-- manager_decisions ------------------------------------------------------------------------
-- The manager's verdict for a completed execution (the terminal artefact of handle()).
CREATE TABLE IF NOT EXISTS manager_decisions (
    id                UUID        NOT NULL,
    tenant_id         UUID        NOT NULL,
    execution_id      UUID        NOT NULL,
    manager_ref       TEXT        NOT NULL,
    outcome           TEXT        NOT NULL,
    summary           TEXT        NOT NULL,
    confidence_score  REAL        NOT NULL,
    confidence        JSONB       NOT NULL DEFAULT '{}'::jsonb,
    evidence          JSONB       NOT NULL DEFAULT '[]'::jsonb,
    merged_result     JSONB       NOT NULL DEFAULT '{}'::jsonb,
    decided_at        TIMESTAMPTZ NOT NULL,
    created_at        TIMESTAMPTZ NOT NULL,
    deleted_at        TIMESTAMPTZ NULL,
    version           INTEGER     NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE (execution_id),
    CHECK (confidence_score >= 0 AND confidence_score <= 1),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_manager_decisions_tenant_outcome
    ON manager_decisions (tenant_id, outcome)
    WHERE deleted_at IS NULL;

-- worker_results ---------------------------------------------------------------------------
-- The structured output each worker returned for one step of an execution.
CREATE TABLE IF NOT EXISTS worker_results (
    id                 UUID        NOT NULL,
    tenant_id          UUID        NOT NULL,
    execution_id       UUID        NOT NULL,
    step_id            UUID        NOT NULL,
    worker_ref         TEXT        NOT NULL,
    confidence         REAL        NOT NULL,
    execution_time_ms  INTEGER     NOT NULL DEFAULT 0,
    execution_cost_micros BIGINT   NOT NULL DEFAULT 0,
    task_result        JSONB       NOT NULL DEFAULT '{}'::jsonb,
    reasoning_summary  TEXT        NOT NULL DEFAULT '',
    resources_used     JSONB       NOT NULL DEFAULT '{}'::jsonb,
    automation_selected TEXT       NULL,
    tools_used         JSONB       NOT NULL DEFAULT '[]'::jsonb,
    warnings           JSONB       NOT NULL DEFAULT '[]'::jsonb,
    errors             JSONB       NOT NULL DEFAULT '[]'::jsonb,
    recommendations    JSONB       NOT NULL DEFAULT '[]'::jsonb,
    logs               JSONB       NOT NULL DEFAULT '[]'::jsonb,
    produced_at        TIMESTAMPTZ NOT NULL,
    created_at         TIMESTAMPTZ NOT NULL,
    deleted_at         TIMESTAMPTZ NULL,
    PRIMARY KEY (id),
    UNIQUE (execution_id, step_id),
    CHECK (confidence >= 0 AND confidence <= 1),
    CHECK (execution_time_ms >= 0),
    CHECK (execution_cost_micros >= 0),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_worker_results_execution
    ON worker_results (tenant_id, execution_id)
    WHERE deleted_at IS NULL;

-- execution_metrics ------------------------------------------------------------------------
-- Rolled-up cost/performance metrics for an execution, for reporting and cost tracking.
CREATE TABLE IF NOT EXISTS execution_metrics (
    id               UUID        NOT NULL,
    tenant_id        UUID        NOT NULL,
    execution_id     UUID        NOT NULL,
    tokens           INTEGER     NOT NULL DEFAULT 0,
    currency_micros  BIGINT      NOT NULL DEFAULT 0,
    wall_ms          INTEGER     NOT NULL DEFAULT 0,
    cpu_ms           INTEGER     NULL,
    step_count       INTEGER     NOT NULL DEFAULT 0,
    retry_count      INTEGER     NOT NULL DEFAULT 0,
    provider_breakdown JSONB     NOT NULL DEFAULT '{}'::jsonb,
    captured_at      TIMESTAMPTZ NOT NULL,
    created_at       TIMESTAMPTZ NOT NULL,
    deleted_at       TIMESTAMPTZ NULL,
    PRIMARY KEY (id),
    UNIQUE (execution_id),
    CHECK (tokens >= 0),
    CHECK (currency_micros >= 0),
    CHECK (wall_ms >= 0),
    CHECK (step_count >= 0),
    CHECK (retry_count >= 0),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_execution_metrics_tenant
    ON execution_metrics (tenant_id, captured_at)
    WHERE deleted_at IS NULL;

-- evidence ---------------------------------------------------------------------------------
-- The de-duplicated evidence artefacts backing worker results and manager decisions.
CREATE TABLE IF NOT EXISTS evidence (
    id           UUID        NOT NULL,
    tenant_id    UUID        NOT NULL,
    execution_id UUID        NOT NULL,
    kind         TEXT        NOT NULL,
    reference    TEXT        NOT NULL,
    summary      TEXT        NOT NULL,
    captured_at  TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ NOT NULL,
    deleted_at   TIMESTAMPTZ NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_evidence_execution
    ON evidence (tenant_id, execution_id)
    WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_evidence_dedupe
    ON evidence (execution_id, kind, reference)
    WHERE deleted_at IS NULL;
