<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Billing\Domain\FeatureCatalog;
use HaHireAI\Shared\Ulid;

/**
 * Platform-set pricing (docs/WALLET_AND_BILLING.md §3). The seat price is a scalar
 * in `pricing_catalog`; feature prices live in `billing_features`. Only a System
 * Owner with `system.pricing.manage` edits these — workspace owners never set
 * prices, they only compose and pay at these prices.
 */
final class PricingCatalog
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Idempotently seed the seat price and the default feature catalog. */
    public function seedDefaults(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        if ($this->connection->selectOne('SELECT id FROM pricing_catalog WHERE `key` = ?', ['seat']) === null) {
            $this->connection->statement(
                'INSERT INTO pricing_catalog (id, `key`, unit_price_cents, currency, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
                [Ulid::generate(), 'seat', FeatureCatalog::defaultSeatPriceCents(), 'USD', $now, $now],
            );
        }

        foreach (FeatureCatalog::defaults() as $f) {
            if ($this->connection->selectOne('SELECT id FROM billing_features WHERE `key` = ?', [$f['key']]) !== null) {
                continue;
            }
            $this->connection->statement(
                'INSERT INTO billing_features (id, `key`, name, description, category, price_cents, sort, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [Ulid::generate(), $f['key'], $f['name'], $f['description'], $f['category'], $f['price_cents'], $f['sort'], $now, $now],
            );
        }
    }

    /** The per-seat monthly price in cents. */
    public function seatPriceCents(): int
    {
        $row = $this->connection->selectOne('SELECT unit_price_cents FROM pricing_catalog WHERE `key` = ? AND is_active = 1', ['seat']);

        return $row !== null ? (int) $row['unit_price_cents'] : FeatureCatalog::defaultSeatPriceCents();
    }

    /** Monthly price in cents for a feature key (0 for basics / unknown). */
    public function featurePriceCents(string $featureKey): int
    {
        $row = $this->connection->selectOne('SELECT price_cents FROM billing_features WHERE `key` = ? AND is_active = 1', [$featureKey]);

        return $row !== null ? (int) $row['price_cents'] : 0;
    }

    /**
     * Unified unit-price lookup: `seat` or `feature.<key>`
     * (docs/WALLET_AND_BILLING.md §3).
     */
    public function unitPrice(string $key): int
    {
        if ($key === 'seat') {
            return $this->seatPriceCents();
        }
        if (str_starts_with($key, 'feature.')) {
            return $this->featurePriceCents(substr($key, 8));
        }

        return 0;
    }

    /**
     * All features for the composer/pricing screen, ordered.
     *
     * @return list<array<string,mixed>>
     */
    public function features(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';

        return $this->connection->select("SELECT * FROM billing_features {$where} ORDER BY sort ASC, name ASC");
    }

    /** Just the premium (priced) features shown in the composer & add-ons list. */
    public function premiumFeatures(): array
    {
        return array_values(array_filter($this->features(), static fn (array $f): bool => (string) $f['category'] === 'premium'));
    }

    public function setSeatPrice(int $cents): void
    {
        $cents = max(0, $cents);
        $now = gmdate('Y-m-d H:i:s');
        if ($this->connection->selectOne('SELECT id FROM pricing_catalog WHERE `key` = ?', ['seat']) === null) {
            $this->connection->statement(
                'INSERT INTO pricing_catalog (id, `key`, unit_price_cents, currency, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
                [Ulid::generate(), 'seat', $cents, 'USD', $now, $now],
            );

            return;
        }
        $this->connection->statement('UPDATE pricing_catalog SET unit_price_cents = ?, updated_at = ? WHERE `key` = ?', [$cents, $now, 'seat']);
    }

    public function setFeaturePrice(string $featureKey, int $cents): void
    {
        $this->connection->statement(
            'UPDATE billing_features SET price_cents = ?, updated_at = ? WHERE `key` = ?',
            [max(0, $cents), gmdate('Y-m-d H:i:s'), $featureKey],
        );
    }
}
