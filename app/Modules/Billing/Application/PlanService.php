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
