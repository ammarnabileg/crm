<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/** Issues and records invoices (docs/BILLING_PLATFORM.md §5). */
final class InvoiceService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param  list<array{label: string, amount_cents: int}>  $lineItems
     */
    public function issue(string $workspaceId, string $subscriptionId, int $amountCents, string $currency, ?string $periodStart, ?string $periodEnd, array $lineItems, string $status = 'open'): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->statement(
            'INSERT INTO invoices (id, workspace_id, subscription_id, number, amount_cents, currency, status, period_start, period_end, line_items, issued_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $subscriptionId, $this->number(), $amountCents, $currency, $status,
                $periodStart, $periodEnd, json_encode($lineItems), $now, $now,
            ],
        );

        return $id;
    }

    public function markPaid(string $invoiceId, string $providerRef): void
    {
        $this->connection->statement(
            "UPDATE invoices SET status = 'paid', provider_ref = ?, paid_at = ? WHERE id = ?",
            [$providerRef, gmdate('Y-m-d H:i:s'), $invoiceId],
        );
    }

    public function markVoid(string $invoiceId): void
    {
        $this->connection->statement("UPDATE invoices SET status = 'void' WHERE id = ?", [$invoiceId]);
    }

    /** @return list<array<string,mixed>> */
    public function listForWorkspace(string $workspaceId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            'SELECT * FROM invoices WHERE workspace_id = ? ORDER BY created_at DESC LIMIT ' . $limit,
            [$workspaceId],
        );
    }

    private function number(): string
    {
        return 'INV-' . gmdate('Ym') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
}
