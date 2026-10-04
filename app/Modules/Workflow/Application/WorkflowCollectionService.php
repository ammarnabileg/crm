<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Dynamic Collections — per-workspace custom data shapes the no-code Database
 * nodes read and write, WITHOUT raw SQL or new MySQL tables (the architecture
 * stays fixed). A collection is a named set of fields; records are JSON rows
 * scoped to the workspace (docs/WORKFLOW_ENGINE.md).
 */
final class WorkflowCollectionService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  list<array{key:string,label:string,type:string}>  $fields
     */
    public function createCollection(string $workspaceId, string $name, array $fields = [], ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workflow_collections (id, workspace_id, `key`, name, fields, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $this->slug($name), $name, json_encode(array_values($fields)), $createdBy, $now, $now],
        );

        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function listCollections(string $workspaceId): array
    {
        $rows = $this->connection->select(
            'SELECT c.*, (SELECT COUNT(*) FROM workflow_collection_records r WHERE r.collection_id = c.id AND r.deleted_at IS NULL) AS records
               FROM workflow_collections c WHERE c.workspace_id = ? ORDER BY c.name ASC',
            [$workspaceId],
        );
        foreach ($rows as &$row) {
            $row['fields'] = json_decode((string) $row['fields'], true) ?: [];
        }

        return $rows;
    }

    /** @return array<string,mixed>|null one collection by id, fields decoded */
    public function findById(string $workspaceId, string $id): ?array
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM workflow_collections WHERE workspace_id = ? AND id = ?',
            [$workspaceId, $id],
        );
        if ($row !== null) {
            $row['fields'] = json_decode((string) $row['fields'], true) ?: [];
        }

        return $row;
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $workspaceId, string $key): ?array
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM workflow_collections WHERE workspace_id = ? AND `key` = ?',
            [$workspaceId, $this->slug($key)],
        );
        if ($row !== null) {
            $row['fields'] = json_decode((string) $row['fields'], true) ?: [];
        }

        return $row;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function createRecord(string $workspaceId, string $collectionId, array $data, ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workflow_collection_records (id, workspace_id, collection_id, data, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $collectionId, json_encode($data), $createdBy, $now, $now],
        );

        return $id;
    }

    /** @param array<string,mixed> $data */
    public function updateRecord(string $workspaceId, string $recordId, array $data): void
    {
        $this->connection->statement(
            'UPDATE workflow_collection_records SET data = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [json_encode($data), gmdate('Y-m-d H:i:s'), $recordId, $workspaceId],
        );
    }

    public function deleteRecord(string $workspaceId, string $recordId): void
    {
        $this->connection->statement(
            'UPDATE workflow_collection_records SET deleted_at = ? WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $recordId, $workspaceId],
        );
    }

    public function restoreRecord(string $workspaceId, string $recordId): void
    {
        $this->connection->statement(
            'UPDATE workflow_collection_records SET deleted_at = NULL, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [gmdate('Y-m-d H:i:s'), $recordId, $workspaceId],
        );
    }

    /** @return list<array<string,mixed>> records (decoded), newest first */
    public function listRecords(string $workspaceId, string $collectionId, bool $withTrashed = false): array
    {
        $where = $withTrashed ? '' : 'AND deleted_at IS NULL';
        $rows = $this->connection->select(
            "SELECT * FROM workflow_collection_records WHERE workspace_id = ? AND collection_id = ? {$where} ORDER BY created_at DESC",
            [$workspaceId, $collectionId],
        );
        foreach ($rows as &$row) {
            $row['data'] = json_decode((string) $row['data'], true) ?: [];
        }

        return $rows;
    }

    public function countRecords(string $workspaceId, string $collectionId): int
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS n FROM workflow_collection_records WHERE workspace_id = ? AND collection_id = ? AND deleted_at IS NULL',
            [$workspaceId, $collectionId],
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Find the first record whose data matches all given key/value pairs.
     *
     * @param  array<string,mixed>  $match
     * @return array<string,mixed>|null
     */
    public function findRecord(string $workspaceId, string $collectionId, array $match): ?array
    {
        foreach ($this->listRecords($workspaceId, $collectionId) as $record) {
            $ok = true;
            foreach ($match as $k => $v) {
                if (($record['data'][$k] ?? null) != $v) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $record;
            }
        }

        return null;
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));

        return $slug === '' ? 'collection' : $slug;
    }
}
