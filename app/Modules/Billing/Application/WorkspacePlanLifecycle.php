<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Advances composed-plan terms (docs/WALLET_AND_BILLING.md §7). Driven by the ops
 * tick (`php bin/console.php billing:tick`); `$nowTs` is injectable for tests.
 * On `period_end` it auto-renews from the wallet — success rolls the term forward
 * a month, failure locks the workspace. Add-ons simply expire (their `expires_at`
 * stops counting in WorkspacePlanService::activeAddons).
 */
final class WorkspacePlanLifecycle
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PlanComposer $composer,
    ) {
    }

    /**
     * Renew every active plan whose period has elapsed.
     *
     * @return array{processed:int, renewed:int, locked:int}
     */
    public function tick(?int $nowTs = null): array
    {
        $nowTs ??= time();
        $nowSql = gmdate('Y-m-d H:i:s', $nowTs);

        $due = $this->connection->select(
            "SELECT workspace_id FROM workspace_plans
              WHERE status = 'active' AND period_end IS NOT NULL AND period_end <= ?",
            [$nowSql],
        );

        $renewed = 0;
        $locked = 0;
        foreach ($due as $row) {
            if ($this->composer->renew((string) $row['workspace_id'], $nowTs)) {
                $renewed++;
            } else {
                $locked++;
            }
        }

        return ['processed' => count($due), 'renewed' => $renewed, 'locked' => $locked];
    }
}
