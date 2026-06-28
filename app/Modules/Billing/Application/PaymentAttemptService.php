<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Records every charge attempt (success or failure) and reads them back for the
 * System Owner's billing report. Failures keep the gateway error code/message so
 * the report can explain the cause and remedy (docs/BILLING_PLATFORM.md).
 */
final class PaymentAttemptService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(
        ?string $workspaceId,
        ?string $planId,
        int $amountCents,
        string $currency,
        string $status,
        string $provider,
        ?string $reference = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        $this->connection->statement(
            'INSERT INTO payment_attempts
                (id, workspace_id, plan_id, amount_cents, currency, status, provider, reference, error_code, error_message, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Ulid::generate(), $workspaceId, $planId, $amountCents, $currency, $status, $provider,
                $reference, $errorCode, $errorMessage, gmdate('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>> attempts (newest first), optionally
     *         filtered by status, enriched with workspace + plan names
     */
    public function recent(int $limit = 100, string $status = ''): array
    {
        $limit = max(1, min(500, $limit));
        $where = '';
        $bindings = [];
        if ($status === 'success' || $status === 'failed') {
            $where = 'WHERE pa.status = ?';
            $bindings[] = $status;
        }

        return $this->connection->select(
            'SELECT pa.id, pa.workspace_id, pa.plan_id, pa.amount_cents, pa.currency, pa.status,
                    pa.provider, pa.reference, pa.error_code, pa.error_message, pa.created_at,
                    w.name AS workspace, p.name AS plan
               FROM payment_attempts pa
               LEFT JOIN workspaces w ON w.id = pa.workspace_id
               LEFT JOIN plans p ON p.id = pa.plan_id
               ' . $where . '
              ORDER BY pa.created_at DESC LIMIT ' . $limit,
            $bindings,
        );
    }

    /** @return array{total:int, success:int, failed:int, charged_cents:int} */
    public function stats(): array
    {
        $row = $this->connection->selectOne(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'success'), 0) AS success,
                COALESCE(SUM(status = 'failed'), 0) AS failed,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount_cents ELSE 0 END), 0) AS charged_cents
             FROM payment_attempts",
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'success' => (int) ($row['success'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'charged_cents' => (int) ($row['charged_cents'] ?? 0),
        ];
    }
}
