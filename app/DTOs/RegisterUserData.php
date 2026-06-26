<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Validated input for registering a user (and optionally naming their company).
 * Built from a validated request; the service trusts only this object.
 */
final class RegisterUserData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $companyName = null,
        public readonly string $locale = 'en',
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $company = isset($data['company_name']) ? trim((string) $data['company_name']) : '';

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            email: mb_strtolower(trim((string) ($data['email'] ?? ''))),
            password: (string) ($data['password'] ?? ''),
            companyName: $company !== '' ? $company : null,
            locale: (string) ($data['locale'] ?? 'en'),
        );
    }
}
