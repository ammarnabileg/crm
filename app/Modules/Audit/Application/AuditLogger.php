<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Audit\Application;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Append-only audit logger (docs/AUDIT_POLICY.md, docs/AUDIT_EVENTS.md).
 * who (actor) / when / where (workspace + ip) / what (action + entity + changes).
 * Never logs secrets or full PII payloads — only references and changed fields.
 * Public surface: the AuditRecorder contract.
 */
final class AuditLogger implements AuditRecorder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  array{workspace_id?: ?string, actor_user_id?: ?string, entity_type?: ?string, entity_id?: ?string, ip?: ?string, changes?: array<string,mixed>}  $context
     */
    public function record(string $action, array $context = []): void
    {
        $this->connection->statement(
            'INSERT INTO audit_logs (id, workspace_id, actor_user_id, action, entity_type, entity_id, ip_address, changes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Ulid::generate(),
                $context['workspace_id'] ?? null,
                $context['actor_user_id'] ?? null,
                $action,
                $context['entity_type'] ?? null,
                $context['entity_id'] ?? null,
                $context['ip'] ?? null,
                isset($context['changes']) ? json_encode($context['changes']) : null,
                gmdate('Y-m-d H:i:s'),
            ],
        );
    }
}
