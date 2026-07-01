<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo;

use Nizam\Platform\Plugin\Port\PluginRepository;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginKind;
use Nizam\Platform\Plugin\RegisteredPlugin;
use PDO;

/**
 * A PDO-backed {@see PluginRepository} for SQLite and PostgreSQL.
 *
 * Plugins are stored one row per aggregate in `plugin_registry`; the published manifest is persisted
 * as a single JSON document (JSONB in Postgres, TEXT-encoded JSON in SQLite) via {@see PluginHydrator}.
 * Every statement is parameterised and every live read filters `deleted_at IS NULL`, so an uninstalled
 * (soft-deleted) plugin never appears in {@see self::ofName()}, {@see self::all()},
 * {@see self::byKind()} or {@see self::enabled()} — while remaining addressable by id for history.
 * A plugin may be tenant-scoped (`tenant_id` set) or global (`tenant_id` null); both live in the same
 * table and the manifest name is unique among live rows. Writes upsert on the primary key using a
 * portable "update, else insert" strategy that works identically on both drivers.
 */
final class PdoPluginRepository implements PluginRepository
{
    /**
     * @param PDO            $connection The database connection (SQLite or PostgreSQL).
     * @param PluginHydrator $hydrator   The row <-> aggregate translator.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly PluginHydrator $hydrator,
    ) {
    }

    /**
     * Persist a plugin, inserting when new and updating in place otherwise.
     */
    public function save(RegisteredPlugin $plugin): void
    {
        $row = $this->hydrator->toRow($plugin);

        $update = $this->connection->prepare(
            'UPDATE plugin_registry
                SET tenant_id = :tenant_id,
                    name = :name,
                    display_name = :display_name,
                    kind = :kind,
                    plugin_version = :plugin_version,
                    state = :state,
                    manifest = :manifest,
                    source = :source,
                    failure_reason = :failure_reason,
                    health_level = :health_level,
                    health_message = :health_message,
                    health_checked_at = :health_checked_at,
                    enabled_at = :enabled_at,
                    updated_at = :updated_at,
                    updated_by = :updated_by,
                    deleted_at = :deleted_at,
                    version = :version
              WHERE id = :id',
        );
        $update->execute([
            'tenant_id' => $row['tenant_id'],
            'name' => $row['name'],
            'display_name' => $row['display_name'],
            'kind' => $row['kind'],
            'plugin_version' => $row['plugin_version'],
            'state' => $row['state'],
            'manifest' => $row['manifest'],
            'source' => $row['source'],
            'failure_reason' => $row['failure_reason'],
            'health_level' => $row['health_level'],
            'health_message' => $row['health_message'],
            'health_checked_at' => $row['health_checked_at'],
            'enabled_at' => $row['enabled_at'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'],
            'deleted_at' => $row['deleted_at'],
            'version' => $row['version'],
            'id' => $row['id'],
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO plugin_registry
                (id, tenant_id, name, display_name, kind, plugin_version, state, manifest, source,
                 failure_reason, health_level, health_message, health_checked_at,
                 installed_at, enabled_at, updated_at, created_at, created_by, updated_by,
                 deleted_at, version)
             VALUES
                (:id, :tenant_id, :name, :display_name, :kind, :plugin_version, :state, :manifest, :source,
                 :failure_reason, :health_level, :health_message, :health_checked_at,
                 :installed_at, :enabled_at, :updated_at, :created_at, :created_by, :updated_by,
                 :deleted_at, :version)',
        );
        $insert->execute([
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'name' => $row['name'],
            'display_name' => $row['display_name'],
            'kind' => $row['kind'],
            'plugin_version' => $row['plugin_version'],
            'state' => $row['state'],
            'manifest' => $row['manifest'],
            'source' => $row['source'],
            'failure_reason' => $row['failure_reason'],
            'health_level' => $row['health_level'],
            'health_message' => $row['health_message'],
            'health_checked_at' => $row['health_checked_at'],
            'installed_at' => $row['installed_at'],
            'enabled_at' => $row['enabled_at'],
            'updated_at' => $row['updated_at'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
            'deleted_at' => $row['deleted_at'],
            'version' => $row['version'],
        ]);
    }

    /**
     * Load a plugin by its identity, or null when none exists.
     *
     * Addressable regardless of soft-delete: an uninstalled plugin is still reachable by id.
     */
    public function ofId(PluginId $id): ?RegisteredPlugin
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM plugin_registry WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id->toString()]);

        return $this->hydrateOne($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Load the live plugin with the given manifest name, or null when none exists.
     */
    public function ofName(string $name): ?RegisteredPlugin
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM plugin_registry
              WHERE name = :name AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute(['name' => $name]);

        return $this->hydrateOne($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * All live registered plugins, ordered by name for stable listing.
     *
     * @return list<RegisteredPlugin>
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT * FROM plugin_registry WHERE deleted_at IS NULL ORDER BY name ASC',
        );

        return $this->hydrateAll($statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * All live registered plugins of a given kind.
     *
     * @return list<RegisteredPlugin>
     */
    public function byKind(PluginKind $kind): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM plugin_registry
              WHERE kind = :kind AND deleted_at IS NULL
              ORDER BY name ASC',
        );
        $statement->execute(['kind' => $kind->value]);

        return $this->hydrateAll($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * All currently-enabled registered plugins.
     *
     * @return list<RegisteredPlugin>
     */
    public function enabled(): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM plugin_registry
              WHERE state = :state AND deleted_at IS NULL
              ORDER BY name ASC',
        );
        $statement->execute(['state' => \Nizam\Platform\Plugin\PluginState::Enabled->value]);

        return $this->hydrateAll($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Hydrate a fetched row into an aggregate, or return null when there is no row.
     *
     * @param array<string, mixed>|false $row
     */
    private function hydrateOne(array|false $row): ?RegisteredPlugin
    {
        if ($row === false) {
            return null;
        }

        return $this->hydrator->fromRow($row);
    }

    /**
     * Hydrate a set of fetched rows into aggregates.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<RegisteredPlugin>
     */
    private function hydrateAll(array $rows): array
    {
        $plugins = [];
        foreach ($rows as $row) {
            $plugins[] = $this->hydrator->fromRow($row);
        }

        return $plugins;
    }
}
