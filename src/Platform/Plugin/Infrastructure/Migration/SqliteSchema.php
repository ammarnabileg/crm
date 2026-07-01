<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Migration;

use PDO;

/**
 * The runnable SQLite equivalent of `001_create_plugin_tables.sql` for integration tests.
 *
 * The Postgres migration is the design source of record; this class provides a structurally
 * equivalent schema an in-memory SQLite connection can run, so the PDO adapters can be exercised
 * end-to-end without a database server. It mirrors the same tables (`plugin_registry`,
 * `plugin_versions`, `plugin_dependencies`, `plugin_health`), the nullable `tenant_id` for global
 * plugins, soft-delete columns, audit columns, the optimistic-version column, the JSON manifest column
 * (stored as TEXT in SQLite), the partial unique/lookup indexes (including `name` unique among live
 * rows), and the foreign keys. Keep it in step with the SQL file.
 */
final class SqliteSchema
{
    /**
     * Apply the full Plugin schema to a connection, idempotently.
     *
     * Enables foreign-key enforcement (off by default in SQLite) and creates every table and index if
     * it does not already exist.
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
            // plugin_registry --------------------------------------------------
            'CREATE TABLE IF NOT EXISTS plugin_registry (
                id                TEXT    NOT NULL,
                tenant_id         TEXT    NULL,
                name              TEXT    NOT NULL,
                display_name      TEXT    NOT NULL,
                kind              TEXT    NOT NULL,
                plugin_version    TEXT    NOT NULL,
                state             TEXT    NOT NULL,
                manifest          TEXT    NOT NULL,
                source            TEXT    NOT NULL,
                failure_reason    TEXT    NULL,
                health_level      TEXT    NULL,
                health_message    TEXT    NULL,
                health_checked_at TEXT    NULL,
                installed_at      TEXT    NOT NULL,
                enabled_at        TEXT    NULL,
                updated_at        TEXT    NOT NULL,
                created_at        TEXT    NOT NULL,
                created_by        TEXT    NOT NULL,
                updated_by        TEXT    NOT NULL,
                deleted_at        TEXT    NULL,
                version           INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                CHECK (version >= 0)
            )',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_plugin_registry_name
                ON plugin_registry (name)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_plugin_registry_kind
                ON plugin_registry (kind)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_plugin_registry_state
                ON plugin_registry (state)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_plugin_registry_tenant
                ON plugin_registry (tenant_id)
                WHERE deleted_at IS NULL',

            // plugin_versions --------------------------------------------------
            'CREATE TABLE IF NOT EXISTS plugin_versions (
                id             TEXT    NOT NULL,
                plugin_id      TEXT    NOT NULL,
                tenant_id      TEXT    NULL,
                name           TEXT    NOT NULL,
                plugin_version TEXT    NOT NULL,
                manifest       TEXT    NOT NULL,
                source         TEXT    NOT NULL,
                is_current     INTEGER NOT NULL DEFAULT 1,
                created_at     TEXT    NOT NULL,
                created_by     TEXT    NOT NULL,
                deleted_at     TEXT    NULL,
                PRIMARY KEY (id),
                UNIQUE (plugin_id, plugin_version),
                FOREIGN KEY (plugin_id) REFERENCES plugin_registry (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_plugin_versions_plugin
                ON plugin_versions (plugin_id)
                WHERE deleted_at IS NULL',

            // plugin_dependencies ----------------------------------------------
            'CREATE TABLE IF NOT EXISTS plugin_dependencies (
                id                 TEXT    NOT NULL,
                plugin_id          TEXT    NOT NULL,
                tenant_id          TEXT    NULL,
                dependency_name    TEXT    NOT NULL,
                version_constraint TEXT    NOT NULL,
                optional           INTEGER NOT NULL DEFAULT 0,
                created_at         TEXT    NOT NULL,
                deleted_at         TEXT    NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (plugin_id) REFERENCES plugin_registry (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_plugin_dependencies_plugin
                ON plugin_dependencies (plugin_id)
                WHERE deleted_at IS NULL',
            'CREATE INDEX IF NOT EXISTS idx_plugin_dependencies_name
                ON plugin_dependencies (dependency_name)
                WHERE deleted_at IS NULL',

            // plugin_health ----------------------------------------------------
            'CREATE TABLE IF NOT EXISTS plugin_health (
                id         TEXT    NOT NULL,
                plugin_id  TEXT    NOT NULL,
                tenant_id  TEXT    NULL,
                level      TEXT    NOT NULL,
                message    TEXT    NOT NULL,
                checked_at TEXT    NOT NULL,
                created_at TEXT    NOT NULL,
                deleted_at TEXT    NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (plugin_id) REFERENCES plugin_registry (id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_plugin_health_plugin_checked
                ON plugin_health (plugin_id, checked_at)
                WHERE deleted_at IS NULL',
        ];
    }
}
