<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;
use HaHireAI\Shared\Ulid;

/**
 * The per-workspace prepaid wallet and its append-only ledger
 * (docs/WALLET_AND_BILLING.md §1–§2). The wallet is the single source of truth
 * for a company's credit balance: every credit/debit is atomic (the wallet row is
 * locked FOR UPDATE) and writes one immutable `wallet_transactions` row carrying
 * the resulting balance. No debit may take the balance below zero.
 */
final class WalletService
{
    /** Valid ledger sources (for traceability in the billing history). */
    public const SOURCES = ['fawaterak', 'plan', 'addon', 'seat', 'renewal', 'system'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** Ensure a wallet row exists for the workspace; returns its id. */
    public function ensure(string $workspaceId, string $currency = 'USD'): string
    {
        $row = $this->connection->selectOne('SELECT id FROM wallets WHERE workspace_id = ?', [$workspaceId]);
        if ($row !== null) {
            return (string) $row['id'];
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO wallets (id, workspace_id, balance_cents, currency, created_at, updated_at) VALUES (?, ?, 0, ?, ?, ?)',
            [$id, $workspaceId, $currency, $now, $now],
        );

        return $id;
    }

    /** Current balance in cents (0 when no wallet exists yet). */
    public function balance(string $workspaceId): int
    {
        $row = $this->connection->selectOne('SELECT balance_cents FROM wallets WHERE workspace_id = ?', [$workspaceId]);

        return $row !== null ? (int) $row['balance_cents'] : 0;
    }

    /** True if the wallet can fund a charge of $amountCents. */
    public function canAfford(string $workspaceId, int $amountCents): bool
    {
        return $this->balance($workspaceId) >= max(0, $amountCents);
    }

    /**
     * Add credits (a positive amount). Records a ledger row and returns the new
     * balance. Used by the Fawaterak top-up and by refunds/adjustments.
     */
    public function credit(
        string $workspaceId,
        int $amountCents,
        string $source,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $actorUserId = null,
        string $type = 'topup',
    ): int {
        if ($amountCents <= 0) {
            throw new BillingException('Credit amount must be positive.');
        }

        return $this->move($workspaceId, $amountCents, $type, $source, $referenceId, $description, $actorUserId);
    }

    /**
     * Spend credits (a positive amount, debited). Throws when the balance is
     * insufficient — callers surface "top up first". Records a ledger row and
     * returns the new balance.
     */
    public function debit(
        string $workspaceId,
        int $amountCents,
        string $source,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $actorUserId = null,
        string $type = 'charge',
    ): int {
        if ($amountCents < 0) {
            throw new BillingException('Debit amount must not be negative.');
        }

        return $this->move($workspaceId, -$amountCents, $type, $source, $referenceId, $description, $actorUserId);
    }

    /**
     * Most recent ledger rows for the billing history (newest first).
     *
     * @return list<array<string,mixed>>
     */
    public function transactions(string $workspaceId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return $this->connection->select(
            "SELECT * FROM wallet_transactions WHERE workspace_id = ? ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            [$workspaceId],
        );
    }

    /**
     * Atomically apply a signed delta to the wallet and append the ledger row.
     * The wallet row is locked FOR UPDATE so concurrent charges cannot race the
     * balance below zero.
     */
    private function move(
        string $workspaceId,
        int $deltaCents,
        string $type,
        string $source,
        ?string $referenceId,
        ?string $description,
        ?string $actorUserId,
    ): int {
        if (! in_array($source, self::SOURCES, true)) {
            throw new BillingException("Unknown wallet source [{$source}].");
        }

        $this->ensure($workspaceId);

        return (int) $this->connection->transaction(function (Connection $db) use (
            $workspaceId, $deltaCents, $type, $source, $referenceId, $description, $actorUserId
        ): int {
            $row = $db->selectOne('SELECT id, balance_cents FROM wallets WHERE workspace_id = ? FOR UPDATE', [$workspaceId]);
            if ($row === null) {
                throw new BillingException('Wallet not found.');
            }

            $current = (int) $row['balance_cents'];
            $after = $current + $deltaCents;
            if ($after < 0) {
                throw new BillingException('Insufficient wallet balance. Please top up first.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $db->statement('UPDATE wallets SET balance_cents = ?, updated_at = ? WHERE id = ?', [$after, $now, (string) $row['id']]);
            $db->statement(
                'INSERT INTO wallet_transactions
                    (id, workspace_id, type, amount_cents, balance_after_cents, source, reference_id, description, actor_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $type, $deltaCents, $after, $source, $referenceId, $description, $actorUserId, $now],
            );

            return $after;
        });
    }
}
