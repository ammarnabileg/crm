<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The public surface of the (shared) file-attachment service. Other modules
 * depend on this contract — never on the concrete `Files\Application\FileService`
 * — so workspace-scoped, tenant-isolated file storage is consumed as a
 * sanctioned shared service (ARCHITECTURE.md §4). Bound to FileService at boot.
 */
interface FileStorage
{
    /**
     * Persist an uploaded (or provided) file and record it. Returns the file id.
     */
    public function store(
        string $workspaceId,
        ?string $uploaderId,
        ?string $entityType,
        ?string $entityId,
        string $tmpPath,
        string $originalName,
        ?int $sizeBytes = null,
        bool $isUpload = true,
    ): string;

    /** @return list<array<string, mixed>> files attached to an entity in this workspace */
    public function listForEntity(string $workspaceId, string $entityType, string $entityId): array;

    /** @return list<array<string, mixed>> */
    public function listForWorkspace(string $workspaceId, int $limit = 100): array;

    /** @return array<string, mixed>|null a file row, scoped to the workspace */
    public function find(string $workspaceId, string $fileId): ?array;

    /** Read the bytes of a workspace file, or null if missing. */
    public function read(string $workspaceId, string $fileId): ?string;

    public function delete(string $workspaceId, string $fileId): void;
}
