<?php

declare(strict_types=1);

namespace App\Contracts\Audit;

/**
 * Audit trail contract. Important actions are recorded with the actor, tenant,
 * IP, and device — and, for changes, the old and new values (docs/47 EAS-7,
 * docs/38-Audit-System).
 *
 * @phpstan-type AuditContext array{
 *   company_id?: int|null, user_id?: int|null, description?: string,
 *   subject_type?: string|null, subject_id?: int|null, properties?: array
 * }
 */
interface AuditLogger
{
    /**
     * Record an action (no before/after state).
     *
     * @param array<string,mixed> $context
     */
    public function log(string $action, array $context = []): void;

    /**
     * Record a change, capturing old and new attribute values.
     *
     * @param array<string,mixed>|null $old
     * @param array<string,mixed>|null $new
     * @param array<string,mixed> $context
     */
    public function logChange(string $action, ?array $old, ?array $new, array $context = []): void;
}
