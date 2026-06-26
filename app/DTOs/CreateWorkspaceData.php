<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Validated input for provisioning a workspace. The owner id is set by the
 * service from the authenticated user, never trusted from the client.
 */
final class CreateWorkspaceData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly int $ownerId,
        public readonly string $locale = 'en',
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            ownerId: (int) ($data['owner_id'] ?? 0),
            locale: (string) ($data['locale'] ?? 'en'),
        );
    }
}
