<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Counts billable seats for a workspace (docs/WALLET_AND_BILLING.md §5).
 *
 * A billable seat is any **active staff membership except the Owner**. The Owner
 * (workspaces.owner_user_id) is seat #0 and free. Candidates are applicants, not
 * memberships, so they are never counted — a person may be a free Candidate in a
 * million workspaces (Constitution mindset #2, #3).
 */
final class SeatCounter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Active staff members excluding the owner. */
    public function billableSeats(string $workspaceId): int
    {
        $row = $this->connection->selectOne(
            "SELECT COUNT(*) AS c
               FROM memberships m
               JOIN workspaces w ON w.id = m.workspace_id
              WHERE m.workspace_id = ?
                AND m.status = 'active'
                AND m.user_id <> w.owner_user_id",
            [$workspaceId],
        );

        return (int) ($row['c'] ?? 0);
    }
}
