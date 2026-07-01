<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure\Persistence\Pdo;

use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;
use PDO;

/**
 * A PDO-backed, tenant-scoped {@see BehaviorProfileRepository} for SQLite and PostgreSQL.
 *
 * Profiles are stored one row per aggregate in `behavior_profiles`; the append-only revision history
 * and the current trait set are persisted as JSON documents (JSONB in Postgres, TEXT-encoded JSON in
 * SQLite) via {@see BehaviorMapper}. Every statement is parameterized and every read is filtered by
 * `tenant_id` and `deleted_at IS NULL`, so a tenant can never see another tenant's — or a soft-
 * deleted — profile. Writes upsert on the primary key using a portable "update, else insert" strategy
 * that works identically on both drivers.
 */
final class PdoBehaviorProfileRepository implements BehaviorProfileRepository
{
    /**
     * @param PDO           $connection The database connection (SQLite or PostgreSQL).
     * @param BehaviorMapper $mapper     The row <-> aggregate translator.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly BehaviorMapper $mapper,
    ) {
    }

    /**
     * Persist a profile, inserting when new and updating in place otherwise.
     */
    public function save(BehaviorProfile $profile): void
    {
        $row = $this->mapper->profileToRow($profile);

        $update = $this->connection->prepare(
            'UPDATE behavior_profiles
                SET status = :status,
                    current_version = :current_version,
                    current_traits = :current_traits,
                    revisions = :revisions,
                    updated_at = :updated_at,
                    updated_by = :updated_by,
                    version = :version
              WHERE id = :id
                AND tenant_id = :tenant_id',
        );
        $update->execute([
            'status' => $row['status'],
            'current_version' => $row['current_version'],
            'current_traits' => $row['current_traits'],
            'revisions' => $row['revisions'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'],
            'version' => $row['version'],
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO behavior_profiles
                (id, tenant_id, role_id, status, current_version, current_traits, revisions,
                 created_at, updated_at, created_by, updated_by, deleted_at, version)
             VALUES
                (:id, :tenant_id, :role_id, :status, :current_version, :current_traits, :revisions,
                 :created_at, :updated_at, :created_by, :updated_by, NULL, :version)',
        );
        $insert->execute([
            'id' => $row['id'],
            'tenant_id' => $row['tenant_id'],
            'role_id' => $row['role_id'],
            'status' => $row['status'],
            'current_version' => $row['current_version'],
            'current_traits' => $row['current_traits'],
            'revisions' => $row['revisions'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
            'version' => $row['version'],
        ]);
    }

    /**
     * Load a profile by id within a tenant, or null when none exists (or it is soft-deleted).
     */
    public function ofId(TenantId $tenantId, BehaviorProfileId $id): ?BehaviorProfile
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM behavior_profiles
              WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute([
            'id' => $id->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);

        return $this->hydrateOne($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Load the profile bound to a role within a tenant, or null when none exists.
     */
    public function ofRole(TenantId $tenantId, RoleId $roleId): ?BehaviorProfile
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM behavior_profiles
              WHERE tenant_id = :tenant_id AND role_id = :role_id AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute([
            'tenant_id' => $tenantId->toString(),
            'role_id' => $roleId->toString(),
        ]);

        return $this->hydrateOne($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Mint the next identity for a new profile.
     */
    public function nextIdentity(): BehaviorProfileId
    {
        return BehaviorProfileId::generate();
    }

    /**
     * Whether a profile already exists for a role within a tenant.
     */
    public function existsForRole(TenantId $tenantId, RoleId $roleId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM behavior_profiles
              WHERE tenant_id = :tenant_id AND role_id = :role_id AND deleted_at IS NULL
              LIMIT 1',
        );
        $statement->execute([
            'tenant_id' => $tenantId->toString(),
            'role_id' => $roleId->toString(),
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Hydrate a fetched row into an aggregate, or return null when there is no row.
     *
     * @param array<string, mixed>|false $row
     */
    private function hydrateOne(array|false $row): ?BehaviorProfile
    {
        if ($row === false) {
            return null;
        }

        return $this->mapper->profileFromRow($row);
    }
}
