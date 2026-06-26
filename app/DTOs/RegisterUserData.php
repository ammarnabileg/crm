<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Validated input for registering a user (and optionally naming their workspace).
 * Built from a validated request; the service trusts only this object.
 */
final class RegisterUserData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $workspaceName = null,
        public readonly string $locale = 'en',
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $workspace = isset($data['workspace_name']) ? trim((string) $data['workspace_name']) : '';

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            email: mb_strtolower(trim((string) ($data['email'] ?? ''))),
            password: (string) ($data['password'] ?? ''),
            workspaceName: $workspace !== '' ? $workspace : null,
            locale: (string) ($data['locale'] ?? 'en'),
        );
    }
}
