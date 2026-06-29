<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Billing\Domain\PlanCatalog;
use HaHireAI\Shared\Ulid;

/** Reads and seeds the plan catalog (docs/BILLING_PLATFORM.md §2). */
final class PlanService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Idempotently seed/refresh the default plans from the catalog. */
    public function seedDefaults(): void
    {
        foreach (PlanCatalog::defaults() as $plan) {
            $existing = $this->findByCode($plan['code']);
            $now = gmdate('Y-m-d H:i:s');

            if ($existing === null) {
                $this->connection->statement(
                    'INSERT INTO plans (id, code, name, description, price_cents, currency, `interval`, trial_days, features, limits, is_public, sort, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        Ulid::generate(), $plan['code'], $plan['name'], $plan['description'], $plan['price_cents'],
                        $plan['currency'], $plan['interval'], $plan['trial_days'],
                        json_encode($plan['features']), json_encode($plan['limits']), 1, $plan['sort'], $now, $now,
                    ],
                );

                continue;
            }

            $this->connection->statement(
                'UPDATE plans SET name = ?, description = ?, price_cents = ?, currency = ?, `interval` = ?, trial_days = ?, features = ?, limits = ?, sort = ?, updated_at = ? WHERE id = ?',
                [
                    $plan['name'], $plan['description'], $plan['price_cents'], $plan['currency'], $plan['interval'],
                    $plan['trial_days'], json_encode($plan['features']), json_encode($plan['limits']), $plan['sort'], $now, (string) $existing['id'],
                ],
            );
        }
    }

    /** @return list<array<string,mixed>> public plans, decoded, ordered for display */
    public function publicPlans(): array
    {
        $rows = $this->connection->select('SELECT * FROM plans WHERE is_public = 1 ORDER BY sort ASC, price_cents ASC');

        return array_map([$this, 'decode'], $rows);
    }

    /** @return list<array<string,mixed>> every plan (admin view), decoded */
    public function allPlans(): array
    {
        $rows = $this->connection->select('SELECT * FROM plans ORDER BY sort ASC, price_cents ASC');

        return array_map([$this, 'decode'], $rows);
    }

    /**
     * Create a plan. `$data` accepts code/name/description/price_cents/currency/
     * interval/trial_days/features[]/is_public/sort/max_workspaces (+ extra limits).
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO plans (id, code, name, description, price_cents, currency, `interval`, trial_days, features, limits, is_public, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                (string) ($data['code'] ?? ('plan-' . substr($id, -6))),
                (string) ($data['name'] ?? 'Plan'),
                $data['description'] ?? null,
                (int) ($data['price_cents'] ?? 0),
                (string) ($data['currency'] ?? 'USD'),
                $this->interval($data),
                (int) ($data['trial_days'] ?? 0),
                json_encode(array_values((array) ($data['features'] ?? []))),
                json_encode($this->limits($data)),
                ! empty($data['is_public']) ? 1 : 0,
                (int) ($data['sort'] ?? 0),
                $now, $now,
            ],
        );

        return $id;
    }

    /** @param array<string,mixed> $data */
    public function update(string $planId, array $data): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'UPDATE plans SET name = ?, description = ?, price_cents = ?, currency = ?, `interval` = ?, trial_days = ?, features = ?, limits = ?, is_public = ?, sort = ?, updated_at = ? WHERE id = ?',
            [
                (string) ($data['name'] ?? 'Plan'),
                $data['description'] ?? null,
                (int) ($data['price_cents'] ?? 0),
                (string) ($data['currency'] ?? 'USD'),
                $this->interval($data),
                (int) ($data['trial_days'] ?? 0),
                json_encode(array_values((array) ($data['features'] ?? []))),
                json_encode($this->limits($data)),
                ! empty($data['is_public']) ? 1 : 0,
                (int) ($data['sort'] ?? 0),
                $now, $planId,
            ],
        );
    }

    /** @param array<string,mixed> $data */
    private function interval(array $data): string
    {
        $interval = (string) ($data['interval'] ?? 'month');

        return in_array($interval, ['month', 'year', 'none'], true) ? $interval : 'month';
    }

    /** How many accounts/workspaces reference this plan (blocks deletion if > 0). */
    public function usageCount(string $planId): int
    {
        $a = (int) ($this->connection->selectOne('SELECT COUNT(*) AS c FROM account_plans WHERE plan_id = ?', [$planId])['c'] ?? 0);
        $s = (int) ($this->connection->selectOne('SELECT COUNT(*) AS c FROM subscriptions WHERE plan_id = ?', [$planId])['c'] ?? 0);

        return $a + $s;
    }

    /** Delete a plan only if nothing references it. Returns false if in use. */
    public function delete(string $planId): bool
    {
        if ($this->usageCount($planId) > 0) {
            return false;
        }
        $this->connection->statement('DELETE FROM plans WHERE id = ?', [$planId]);

        return true;
    }

    /**
     * Build the limits JSON, always carrying a `workspaces` cap (account-level).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,int>
     */
    private function limits(array $data): array
    {
        $limits = is_array($data['limits'] ?? null) ? $data['limits'] : [];
        $limits['workspaces'] = max(1, (int) ($data['max_workspaces'] ?? $limits['workspaces'] ?? 1));

        return array_map('intval', $limits);
    }

    /** @return array<string,mixed>|null */
    public function find(string $planId): ?array
    {
        $row = $this->connection->selectOne('SELECT * FROM plans WHERE id = ?', [$planId]);

        return $row !== null ? $this->decode($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        $row = $this->connection->selectOne('SELECT * FROM plans WHERE code = ?', [$code]);

        return $row !== null ? $this->decode($row) : null;
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function decode(array $row): array
    {
        $row['features'] = is_string($row['features'] ?? null) ? (array) json_decode((string) $row['features'], true) : [];
        $row['limits'] = is_string($row['limits'] ?? null) ? (array) json_decode((string) $row['limits'], true) : [];

        return $row;
    }
}
