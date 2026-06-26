<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D4 — Subscriptions & Billing (FOREIGN KEYS).
 *
 * Adds every foreign key for the billing tables created in 0022_create_billing.php
 * — anchors (`workspaces`, `users`, `currencies`), the BUILT `plans` /
 * `subscriptions`, intra-domain refs, and the self-ref on `transactions`. Split
 * from the CREATE file so all tables exist before any constraint is added; this
 * removes create-order / cross-domain cycle problems entirely.
 *
 * Each ADD/DROP is guarded by an information_schema check so the file is
 * idempotent. ON UPDATE CASCADE everywhere; ON DELETE per docs/database/05.
 */
return new class extends Migration {
    /**
     * Every FK as [table, column, ref_table, on_delete].
     * ON UPDATE is CASCADE for all (per the domain spec).
     *
     * @var array<int, array{0:string,1:string,2:string,3:string}>
     */
    private array $foreignKeys = [
        // plan_features
        ['plan_features', 'plan_id', 'plans', 'CASCADE'],

        // plan_prices
        ['plan_prices', 'plan_id', 'plans', 'CASCADE'],
        ['plan_prices', 'currency_id', 'currencies', 'RESTRICT'],

        // subscription_statuses
        ['subscription_statuses', 'workspace_id', 'workspaces', 'CASCADE'],

        // subscription_items
        ['subscription_items', 'workspace_id', 'workspaces', 'CASCADE'],
        ['subscription_items', 'subscription_id', 'subscriptions', 'CASCADE'],
        ['subscription_items', 'plan_price_id', 'plan_prices', 'RESTRICT'],
        ['subscription_items', 'currency_id', 'currencies', 'RESTRICT'],

        // subscription_renewals
        ['subscription_renewals', 'workspace_id', 'workspaces', 'CASCADE'],
        ['subscription_renewals', 'subscription_id', 'subscriptions', 'CASCADE'],
        ['subscription_renewals', 'invoice_id', 'invoices', 'SET NULL'],
        ['subscription_renewals', 'currency_id', 'currencies', 'RESTRICT'],

        // trials
        ['trials', 'workspace_id', 'workspaces', 'CASCADE'],
        ['trials', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['trials', 'plan_id', 'plans', 'RESTRICT'],
        ['trials', 'converted_subscription_id', 'subscriptions', 'SET NULL'],

        // invoice_statuses
        ['invoice_statuses', 'workspace_id', 'workspaces', 'CASCADE'],

        // invoices
        ['invoices', 'workspace_id', 'workspaces', 'CASCADE'],
        ['invoices', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['invoices', 'invoice_status_id', 'invoice_statuses', 'RESTRICT'],
        ['invoices', 'currency_id', 'currencies', 'RESTRICT'],
        ['invoices', 'coupon_id', 'coupons', 'SET NULL'],

        // invoice_items
        ['invoice_items', 'workspace_id', 'workspaces', 'CASCADE'],
        ['invoice_items', 'invoice_id', 'invoices', 'CASCADE'],
        ['invoice_items', 'subscription_item_id', 'subscription_items', 'SET NULL'],
        ['invoice_items', 'plan_price_id', 'plan_prices', 'SET NULL'],
        ['invoice_items', 'currency_id', 'currencies', 'RESTRICT'],

        // payment_statuses
        ['payment_statuses', 'workspace_id', 'workspaces', 'CASCADE'],

        // payment_methods
        ['payment_methods', 'workspace_id', 'workspaces', 'CASCADE'],
        ['payment_methods', 'payment_gateway_id', 'payment_gateways', 'RESTRICT'],
        ['payment_methods', 'created_by', 'users', 'SET NULL'],

        // payments
        ['payments', 'workspace_id', 'workspaces', 'CASCADE'],
        ['payments', 'invoice_id', 'invoices', 'SET NULL'],
        ['payments', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['payments', 'payment_status_id', 'payment_statuses', 'RESTRICT'],
        ['payments', 'payment_gateway_id', 'payment_gateways', 'RESTRICT'],
        ['payments', 'payment_method_id', 'payment_methods', 'SET NULL'],
        ['payments', 'currency_id', 'currencies', 'RESTRICT'],
        ['payments', 'created_by', 'users', 'SET NULL'],

        // transactions (immutable ledger; workspace RESTRICT so it never vanishes)
        ['transactions', 'workspace_id', 'workspaces', 'RESTRICT'],
        ['transactions', 'payment_id', 'payments', 'SET NULL'],
        ['transactions', 'invoice_id', 'invoices', 'SET NULL'],
        ['transactions', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['transactions', 'payment_gateway_id', 'payment_gateways', 'RESTRICT'],
        ['transactions', 'currency_id', 'currencies', 'RESTRICT'],
        ['transactions', 'parent_transaction_id', 'transactions', 'RESTRICT'],

        // gateway_events
        ['gateway_events', 'payment_gateway_id', 'payment_gateways', 'RESTRICT'],
        ['gateway_events', 'workspace_id', 'workspaces', 'SET NULL'],
        ['gateway_events', 'payment_id', 'payments', 'SET NULL'],

        // coupons
        ['coupons', 'workspace_id', 'workspaces', 'CASCADE'],
        ['coupons', 'currency_id', 'currencies', 'RESTRICT'],
        ['coupons', 'applies_to_plan_id', 'plans', 'SET NULL'],
        ['coupons', 'created_by', 'users', 'SET NULL'],

        // coupon_redemptions
        ['coupon_redemptions', 'workspace_id', 'workspaces', 'CASCADE'],
        ['coupon_redemptions', 'coupon_id', 'coupons', 'RESTRICT'],
        ['coupon_redemptions', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['coupon_redemptions', 'invoice_id', 'invoices', 'SET NULL'],
        ['coupon_redemptions', 'currency_id', 'currencies', 'RESTRICT'],
        ['coupon_redemptions', 'redeemed_by', 'users', 'SET NULL'],

        // usage_limits
        ['usage_limits', 'plan_id', 'plans', 'CASCADE'],
        ['usage_limits', 'workspace_id', 'workspaces', 'CASCADE'],
        ['usage_limits', 'subscription_id', 'subscriptions', 'CASCADE'],
        ['usage_limits', 'currency_id', 'currencies', 'RESTRICT'],

        // usage_records
        ['usage_records', 'workspace_id', 'workspaces', 'CASCADE'],
        ['usage_records', 'subscription_id', 'subscriptions', 'SET NULL'],
        ['usage_records', 'usage_limit_id', 'usage_limits', 'SET NULL'],
        ['usage_records', 'currency_id', 'currencies', 'RESTRICT'],
    ];

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys as [$table, $column, $ref, $onDelete]) {
            $name = $this->fkName($table, $column);
            if (! $this->hasTable($db, $table) || ! $this->hasTable($db, $ref)) {
                continue;
            }
            if ($this->hasConstraint($db, $table, $name)) {
                continue;
            }
            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) "
                . "REFERENCES `{$ref}` (`id`) ON DELETE {$onDelete} ON UPDATE CASCADE"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->foreignKeys as [$table, $column, $ref, $onDelete]) {
            $name = $this->fkName($table, $column);
            if ($this->hasConstraint($db, $table, $name)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }
        }
    }

    /** Constraint name <table>_<column>_foreign, shortened if it would exceed 64 chars. */
    private function fkName(string $table, string $column): string
    {
        $name = "{$table}_{$column}_foreign";
        if (strlen($name) <= 64) {
            return $name;
        }
        return substr($name, 0, 54) . '_' . substr(md5($name), 0, 8) . '_fk';
    }

    // -----------------------------------------------------------------
    // Idempotency helpers
    // -----------------------------------------------------------------

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }

    private function hasIndex(Database $db, string $table, string $index): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }
};
