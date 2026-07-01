<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Migration;

use PDO;

/**
 * The runnable SQLite equivalent of {@see 001_create_runtime_tables.sql} for integration tests.
 *
 * The Postgres migration is the design source of record; this class provides a structurally equivalent
 * schema an in-memory SQLite connection can run, so the Runtime's PDO adapters (repository, append-only
 * event store, advisory-row lock manager) can be exercised end-to-end without a database server. It
 * mirrors the same tables, tenant scoping, append-only history, advisory-lock TTL row, soft-delete and
 * audit columns, optimistic-version columns, JSON documents (stored as TEXT in SQLite), unique/lookup
 * indexes, and foreign keys. Keep it in step with the SQL file.
 */
final class SqliteSchema
{
    /**
     * Apply the full Runtime schema to a connection, idempotently.
     *
     * Enables foreign-key enforcement (off by default in SQLite) and creates every table and index if it
     * does not already exist.
     *
     * @param PDO $connection An open SQLite connection.
     */
    public static function apply(PDO $connection): void
    {
        $connection->exec('PRAGMA foreign_keys = ON');

        foreach (self::statements() as $statement) {
            $connection->exec($statement);
        }
    }

    /**
     * The ordered DDL statements that build the schema.
     *
     * @return list<string>
     */
    private static function statements(): array
    {
        return [
            // executions --------------------------------------------------------
            'CREATE TABLE IF NOT EXISTS executions (
                id             TEXT    NOT NULL,
                tenant_id      TEXT    NOT NULL,
                user_id        TEXT    NULL,
                department_ref TEXT    NULL,
                manager_ref    TEXT    NULL,
                intent_ref     TEXT    NOT NULL,
                state          TEXT    NOT NULL,
                metadata       TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                cost           TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                performance    TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                timeline       TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                attempts       INTEGER NOT NULL DEFAULT 1,
                created_at     TEXT    NOT NULL,
                updated_at     TEXT    NOT NULL,
                deleted_at     TEXT    NULL,
                version        INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                CHECK (attempts >= 1),
                CHECK (version >= 0)
            )',
            'CREATE INDEX IF NOT EXISTS idx_executions_tenant_state
                ON executions (tenant_id, state)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_executions_tenant_created
                ON executions (tenant_id, created_at)
                WHERE deleted_at IS NULL',

            // execution_history -------------------------------------------------
            'CREATE TABLE IF NOT EXISTS execution_history (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                execution_id TEXT    NOT NULL,
                sequence_no  INTEGER NOT NULL,
                event_name   TEXT    NOT NULL,
                payload      TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                occurred_at  TEXT    NOT NULL,
                recorded_at  TEXT    NOT NULL,
                PRIMARY KEY (id),
                UNIQUE (execution_id, sequence_no),
                CHECK (sequence_no >= 0)
            )',
            'CREATE INDEX IF NOT EXISTS idx_execution_history_execution
                ON execution_history (execution_id, sequence_no)',
            'CREATE INDEX IF NOT EXISTS idx_execution_history_tenant
                ON execution_history (tenant_id, occurred_at)',

            // execution_locks ---------------------------------------------------
            'CREATE TABLE IF NOT EXISTS execution_locks (
                lock_key    TEXT    NOT NULL,
                owner_token TEXT    NOT NULL,
                acquired_at TEXT    NOT NULL,
                ttl_ms      INTEGER NOT NULL,
                expires_at  TEXT    NOT NULL,
                PRIMARY KEY (lock_key),
                CHECK (ttl_ms >= 1)
            )',
            'CREATE INDEX IF NOT EXISTS idx_execution_locks_expires
                ON execution_locks (expires_at)',

            // execution_timeline ------------------------------------------------
            'CREATE TABLE IF NOT EXISTS execution_timeline (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                execution_id TEXT    NOT NULL,
                sequence_no  INTEGER NOT NULL,
                state        TEXT    NOT NULL,
                note         TEXT    NOT NULL,
                entered_at   TEXT    NOT NULL,
                created_at   TEXT    NOT NULL,
                deleted_at   TEXT    NULL,
                PRIMARY KEY (id),
                UNIQUE (execution_id, sequence_no),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_execution_timeline_execution
                ON execution_timeline (tenant_id, execution_id, sequence_no)
                WHERE deleted_at IS NULL',

            // execution_logs ----------------------------------------------------
            'CREATE TABLE IF NOT EXISTS execution_logs (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                execution_id TEXT    NOT NULL,
                level        TEXT    NOT NULL DEFAULT ' . "'info'" . ',
                message      TEXT    NOT NULL,
                logged_at    TEXT    NOT NULL,
                created_at   TEXT    NOT NULL,
                deleted_at   TEXT    NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_execution_logs_execution
                ON execution_logs (tenant_id, execution_id, logged_at)
                WHERE deleted_at IS NULL',

            // manager_decisions -------------------------------------------------
            'CREATE TABLE IF NOT EXISTS manager_decisions (
                id               TEXT    NOT NULL,
                tenant_id        TEXT    NOT NULL,
                execution_id     TEXT    NOT NULL,
                manager_ref      TEXT    NOT NULL,
                outcome          TEXT    NOT NULL,
                summary          TEXT    NOT NULL,
                confidence_score REAL    NOT NULL,
                confidence       TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                evidence         TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                merged_result    TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                decided_at       TEXT    NOT NULL,
                created_at       TEXT    NOT NULL,
                deleted_at       TEXT    NULL,
                version          INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE (execution_id),
                CHECK (confidence_score >= 0 AND confidence_score <= 1),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_manager_decisions_tenant_outcome
                ON manager_decisions (tenant_id, outcome)
                WHERE deleted_at IS NULL',

            // worker_results ----------------------------------------------------
            'CREATE TABLE IF NOT EXISTS worker_results (
                id                    TEXT    NOT NULL,
                tenant_id             TEXT    NOT NULL,
                execution_id          TEXT    NOT NULL,
                step_id               TEXT    NOT NULL,
                worker_ref            TEXT    NOT NULL,
                confidence            REAL    NOT NULL,
                execution_time_ms     INTEGER NOT NULL DEFAULT 0,
                execution_cost_micros INTEGER NOT NULL DEFAULT 0,
                task_result           TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                reasoning_summary     TEXT    NOT NULL DEFAULT ' . "''" . ',
                resources_used        TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                automation_selected   TEXT    NULL,
                tools_used            TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                warnings              TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                errors                TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                recommendations       TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                logs                  TEXT    NOT NULL DEFAULT ' . "'[]'" . ',
                produced_at           TEXT    NOT NULL,
                created_at            TEXT    NOT NULL,
                deleted_at            TEXT    NULL,
                PRIMARY KEY (id),
                UNIQUE (execution_id, step_id),
                CHECK (confidence >= 0 AND confidence <= 1),
                CHECK (execution_time_ms >= 0),
                CHECK (execution_cost_micros >= 0),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_worker_results_execution
                ON worker_results (tenant_id, execution_id)
                WHERE deleted_at IS NULL',

            // execution_metrics -------------------------------------------------
            'CREATE TABLE IF NOT EXISTS execution_metrics (
                id                 TEXT    NOT NULL,
                tenant_id          TEXT    NOT NULL,
                execution_id       TEXT    NOT NULL,
                tokens             INTEGER NOT NULL DEFAULT 0,
                currency_micros    INTEGER NOT NULL DEFAULT 0,
                wall_ms            INTEGER NOT NULL DEFAULT 0,
                cpu_ms             INTEGER NULL,
                step_count         INTEGER NOT NULL DEFAULT 0,
                retry_count        INTEGER NOT NULL DEFAULT 0,
                provider_breakdown TEXT    NOT NULL DEFAULT ' . "'{}'" . ',
                captured_at        TEXT    NOT NULL,
                created_at         TEXT    NOT NULL,
                deleted_at         TEXT    NULL,
                PRIMARY KEY (id),
                UNIQUE (execution_id),
                CHECK (tokens >= 0),
                CHECK (currency_micros >= 0),
                CHECK (wall_ms >= 0),
                CHECK (step_count >= 0),
                CHECK (retry_count >= 0),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_execution_metrics_tenant
                ON execution_metrics (tenant_id, captured_at)
                WHERE deleted_at IS NULL',

            // evidence ----------------------------------------------------------
            'CREATE TABLE IF NOT EXISTS evidence (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                execution_id TEXT    NOT NULL,
                kind         TEXT    NOT NULL,
                reference    TEXT    NOT NULL,
                summary      TEXT    NOT NULL,
                captured_at  TEXT    NOT NULL,
                created_at   TEXT    NOT NULL,
                deleted_at   TEXT    NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (execution_id) REFERENCES executions (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_evidence_execution
                ON evidence (tenant_id, execution_id)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_evidence_dedupe
                ON evidence (execution_id, kind, reference)
                WHERE deleted_at IS NULL',
        ];
    }
}
