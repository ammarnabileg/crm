<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Plugin\HealthLevel;
use Nizam\Platform\Plugin\PluginHealthStatus;
use Nizam\Platform\Plugin\PluginId;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginState;
use Nizam\Platform\Plugin\RegisteredPlugin;
use Nizam\Platform\Support\Json;

/**
 * The single translation point between {@see RegisteredPlugin} aggregates and their persisted rows.
 *
 * PDO adapters speak rows of strings and JSON; the domain speaks value objects, enums and timestamps.
 * This stateless hydrator owns that translation in both directions, so every persisting adapter
 * serialises and rehydrates identically. The manifest is stored as a single JSON document (JSONB in
 * Postgres, TEXT-encoded JSON in SQLite) built from {@see PluginManifest::toArray()} and rebuilt via
 * {@see PluginManifest::fromArray()}; the last-known health is stored as three nullable columns. The
 * aggregate is rebuilt through {@see RegisteredPlugin::reconstitute()}, which records no domain event,
 * since loading a row is not a new business fact.
 */
final class PluginHydrator
{
    /**
     * Flatten a {@see RegisteredPlugin} to a column map ready for an INSERT/UPDATE.
     *
     * `deleted_at` is derived from the lifecycle state: an uninstalled plugin is soft-deleted, marked
     * at its last-updated instant, so it disappears from live lookups while its history is retained.
     *
     * @return array<string, string|int|null>
     *
     * @throws PlatformException When manifest encoding fails.
     */
    public function toRow(RegisteredPlugin $plugin): array
    {
        $manifest = $plugin->manifest();
        $health = $plugin->lastHealth();
        $deletedAt = $plugin->state() === PluginState::Uninstalled
            ? $plugin->updatedAt()->format(DateTimeImmutable::ATOM)
            : null;

        return [
            'id' => $plugin->pluginId()->toString(),
            'tenant_id' => $plugin->tenantId()?->toString(),
            'name' => $manifest->name(),
            'display_name' => $manifest->displayName(),
            'kind' => $manifest->kind()->value,
            'plugin_version' => (string) $manifest->version(),
            'state' => $plugin->state()->value,
            'manifest' => Json::encode($manifest->toArray()),
            'source' => $plugin->source(),
            'failure_reason' => $plugin->failureReason(),
            'health_level' => $health?->level()->value,
            'health_message' => $health?->message(),
            'health_checked_at' => $health?->checkedAt()->format(DateTimeImmutable::ATOM),
            'installed_at' => $plugin->installedAt()->format(DateTimeImmutable::ATOM),
            'enabled_at' => $plugin->enabledAt()?->format(DateTimeImmutable::ATOM),
            'updated_at' => $plugin->updatedAt()->format(DateTimeImmutable::ATOM),
            'created_at' => $plugin->installedAt()->format(DateTimeImmutable::ATOM),
            'created_by' => $manifest->author(),
            'updated_by' => $manifest->author(),
            'deleted_at' => $deletedAt,
            'version' => $plugin->version(),
        ];
    }

    /**
     * Rebuild a {@see RegisteredPlugin} aggregate from a persisted registry row.
     *
     * @param array<string, mixed> $row A row from the plugin_registry table.
     *
     * @throws PlatformException When the row is missing columns or malformed.
     */
    public function fromRow(array $row): RegisteredPlugin
    {
        $tenantRaw = $this->nullableString($row, 'tenant_id');

        return RegisteredPlugin::reconstitute(
            PluginId::fromString($this->string($row, 'id')),
            PluginManifest::fromArray($this->decodeManifest($this->string($row, 'manifest'))),
            PluginState::from($this->string($row, 'state')),
            $tenantRaw === null ? null : TenantId::fromString($tenantRaw),
            $this->string($row, 'source'),
            $this->dateTime($row, 'installed_at'),
            $this->nullableDateTime($row, 'enabled_at'),
            $this->dateTime($row, 'updated_at'),
            $this->health($row),
            $this->nullableString($row, 'failure_reason'),
            $this->int($row, 'version'),
        );
    }

    /**
     * Decode the persisted manifest document into its associative form.
     *
     * @return array<string, mixed>
     *
     * @throws PlatformException When the JSON is invalid.
     */
    private function decodeManifest(string $json): array
    {
        /** @var array<string, mixed> */
        return Json::decode($json);
    }

    /**
     * Rebuild the last-known health from the three health columns, or null when never checked.
     *
     * @param array<string, mixed> $row
     *
     * @throws PlatformException When a health column is malformed.
     */
    private function health(array $row): ?PluginHealthStatus
    {
        $level = $this->nullableString($row, 'health_level');
        $checkedAt = $this->nullableDateTime($row, 'health_checked_at');
        if ($level === null || $checkedAt === null) {
            return null;
        }

        $message = $this->nullableString($row, 'health_message') ?? '';

        return PluginHealthStatus::at(HealthLevel::from($level), $checkedAt, $message);
    }

    /**
     * Read a required string column.
     *
     * @param array<string, mixed> $row
     *
     * @throws PlatformException When absent or not stringable.
     */
    private function string(array $row, string $column): string
    {
        if (!array_key_exists($column, $row) || $row[$column] === null) {
            throw new PlatformException(sprintf('Missing required column "%s" in plugin row.', $column));
        }
        $value = $row[$column];
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new PlatformException(sprintf('Column "%s" must be a scalar string.', $column));
        }

        return (string) $value;
    }

    /**
     * Read a nullable string column.
     *
     * @param array<string, mixed> $row
     */
    private function nullableString(array $row, string $column): ?string
    {
        if (!array_key_exists($column, $row) || $row[$column] === null) {
            return null;
        }
        $value = $row[$column];
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Read a required integer column.
     *
     * @param array<string, mixed> $row
     *
     * @throws PlatformException When absent or non-numeric.
     */
    private function int(array $row, string $column): int
    {
        if (!array_key_exists($column, $row) || !is_numeric($row[$column])) {
            throw new PlatformException(sprintf('Column "%s" must be an integer.', $column));
        }

        return (int) $row[$column];
    }

    /**
     * Read a required timestamp column into an immutable date-time.
     *
     * @param array<string, mixed> $row
     *
     * @throws PlatformException When absent or unparsable.
     */
    private function dateTime(array $row, string $column): DateTimeImmutable
    {
        $value = $this->nullableDateTime($row, $column);
        if ($value === null) {
            throw new PlatformException(sprintf('Column "%s" must be a timestamp.', $column));
        }

        return $value;
    }

    /**
     * Read a nullable timestamp column into an immutable date-time, or null.
     *
     * @param array<string, mixed> $row
     *
     * @throws PlatformException When present but unparsable.
     */
    private function nullableDateTime(array $row, string $column): ?DateTimeImmutable
    {
        if (!array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
            return null;
        }
        if (!is_string($row[$column])) {
            throw new PlatformException(sprintf('Column "%s" must be a timestamp string.', $column));
        }

        try {
            return new DateTimeImmutable($row[$column]);
        } catch (\Exception $e) {
            throw new PlatformException(
                sprintf('Column "%s" is not a valid timestamp: %s', $column, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
