<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The audit/logging shared service contract. Modules record audit events through
 * this contract instead of depending on the concrete `Audit\Application\AuditLogger`
 * (ARCHITECTURE.md §4 — Logging is a sanctioned cross-cutting shared service).
 */
interface AuditRecorder
{
    /**
     * @param  array<string, mixed>  $context  workspace_id, actor_user_id, entity_type, entity_id, changes, ip…
     */
    public function record(string $action, array $context = []): void;
}
