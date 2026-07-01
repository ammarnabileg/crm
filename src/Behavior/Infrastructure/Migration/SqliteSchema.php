<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Migration;

use PDO;

/**
 * The runnable SQLite equivalent of {@see 001_create_behavior_tables.sql} for integration tests.
 *
 * The Postgres migration is the design source of record; this class provides a structurally
 * equivalent schema an in-memory SQLite connection can run, so the PDO adapters can be exercised
 * end-to-end without a database server. It mirrors the same tables, tenant scoping, soft-delete
 * columns, audit columns, optimistic-version columns, JSON trait columns (stored as TEXT in SQLite),
 * partial unique/lookup indexes, and foreign keys. Keep it in step with the SQL file.
 */
final class SqliteSchema
{
    /**
     * Apply the full Behavior schema to a connection, idempotently.
     *
     * Enables foreign-key enforcement (off by default in SQLite) and creates every table and index
     * if it does not already exist.
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
            // behavior_profiles ------------------------------------------------
            'CREATE TABLE IF NOT EXISTS behavior_profiles (
                id              TEXT    NOT NULL,
                tenant_id       TEXT    NOT NULL,
                role_id         TEXT    NOT NULL,
                status          TEXT    NOT NULL,
                current_version INTEGER NOT NULL,
                current_traits  TEXT    NOT NULL,
                revisions       TEXT    NOT NULL,
                created_at      TEXT    NOT NULL,
                updated_at      TEXT    NOT NULL,
                created_by      TEXT    NOT NULL,
                updated_by      TEXT    NOT NULL,
                deleted_at      TEXT    NULL,
                version         INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                CHECK (current_version >= 1),
                CHECK (version >= 0)
            )',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_behavior_profiles_tenant_role
                ON behavior_profiles (tenant_id, role_id)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_behavior_profiles_tenant_role
                ON behavior_profiles (tenant_id, role_id)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_behavior_profiles_tenant_status
                ON behavior_profiles (tenant_id, status)
                WHERE deleted_at IS NULL',

            // behavior_profile_revisions --------------------------------------
            'CREATE TABLE IF NOT EXISTS behavior_profile_revisions (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                profile_id   TEXT    NOT NULL,
                version      INTEGER NOT NULL,
                traits       TEXT    NOT NULL,
                change_log   TEXT    NOT NULL,
                evidence     TEXT    NOT NULL,
                approved_by  TEXT    NOT NULL,
                approved_at  TEXT    NOT NULL,
                created_at   TEXT    NOT NULL,
                created_by   TEXT    NOT NULL,
                deleted_at   TEXT    NULL,
                PRIMARY KEY (id),
                UNIQUE (profile_id, version),
                CHECK (version >= 1),
                FOREIGN KEY (profile_id) REFERENCES behavior_profiles (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_behavior_profile_revisions_tenant_profile
                ON behavior_profile_revisions (tenant_id, profile_id)
                WHERE deleted_at IS NULL',

            // behavior_change_proposals ---------------------------------------
            'CREATE TABLE IF NOT EXISTS behavior_change_proposals (
                id                  TEXT    NOT NULL,
                tenant_id           TEXT    NOT NULL,
                role_id             TEXT    NOT NULL,
                profile_id          TEXT    NOT NULL,
                proposed_traits     TEXT    NOT NULL,
                rationale           TEXT    NOT NULL,
                supporting_evidence TEXT    NOT NULL,
                confidence          REAL    NOT NULL,
                business_impact     TEXT    NOT NULL,
                rollback_to_version INTEGER NULL,
                status              TEXT    NOT NULL,
                proposed_by         TEXT    NOT NULL,
                proposed_at         TEXT    NOT NULL,
                decided_by          TEXT    NULL,
                decided_at          TEXT    NULL,
                created_at          TEXT    NOT NULL,
                updated_at          TEXT    NOT NULL,
                created_by          TEXT    NOT NULL,
                updated_by          TEXT    NOT NULL,
                deleted_at          TEXT    NULL,
                version             INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                CHECK (confidence >= 0 AND confidence <= 1),
                CHECK (rollback_to_version IS NULL OR rollback_to_version >= 1),
                FOREIGN KEY (profile_id) REFERENCES behavior_profiles (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_behavior_change_proposals_tenant_status
                ON behavior_change_proposals (tenant_id, status, proposed_at)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_behavior_change_proposals_tenant_profile
                ON behavior_change_proposals (tenant_id, profile_id)
                WHERE deleted_at IS NULL',

            // behavior_observations -------------------------------------------
            'CREATE TABLE IF NOT EXISTS behavior_observations (
                id           TEXT    NOT NULL,
                tenant_id    TEXT    NOT NULL,
                role_id      TEXT    NOT NULL,
                source_type  TEXT    NOT NULL,
                reference_id TEXT    NOT NULL,
                summary      TEXT    NOT NULL,
                occurred_at  TEXT    NOT NULL,
                weight       REAL    NOT NULL,
                observed_traits TEXT NOT NULL,
                approved_by  TEXT    NULL,
                approved_at  TEXT    NULL,
                created_at   TEXT    NOT NULL,
                created_by   TEXT    NOT NULL,
                deleted_at   TEXT    NULL,
                PRIMARY KEY (id),
                CHECK (weight >= 0 AND weight <= 1)
            )',
            'CREATE INDEX IF NOT EXISTS idx_behavior_observations_tenant_role_approved
                ON behavior_observations (tenant_id, role_id, occurred_at)
                WHERE deleted_at IS NULL AND approved_at IS NOT NULL',
        ];
    }
}
