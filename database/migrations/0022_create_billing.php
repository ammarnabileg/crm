<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D4 — Subscriptions & Billing (CREATE).
 *
 * Creates the NEW billing tables that extend the BUILT `plans` (0009) and
 * `subscriptions` (0010): the normalized plan catalog (`plan_features`,
 * `plan_prices`), subscription children (`subscription_items`,
 * `subscription_renewals`, `trials`), the three config-driven status tables
 * (`subscription_statuses`, `invoice_statuses`, `payment_statuses`), the billing
 * documents (`invoices`, `invoice_items`), the payment / ledger stack
 * (`payments`, `transactions`, `payment_methods`, `payment_gateways`,
 * `gateway_events`), promo codes (`coupons`, `coupon_redemptions`) and metered
 * usage (`usage_records`, `usage_limits`). See docs/database/05.
 *
 * TWO-FILE pattern: this file creates tables + ALL indexes and seeds system
 * defaults, but adds NO foreign keys. FKs live in 0122_fk_billing.php so
 * create-order and cross-domain cycles never break the build. Idempotent via
 * information_schema guards.
 */
return new class extends Migration {
    /** subscription_statuses system defaults: [key, label, color, sort, default, initial, terminal]. */
    private array $subscriptionStatuses = [
        ['trialing', 'Trialing', '#6366f1', 1, 1, 1, 0],
        ['active', 'Active', '#16a34a', 2, 0, 0, 0],
        ['past_due', 'Past Due', '#f59e0b', 3, 0, 0, 0],
        ['paused', 'Paused', '#64748b', 4, 0, 0, 0],
        ['canceled', 'Canceled', '#dc2626', 5, 0, 0, 1],
        ['expired', 'Expired', '#991b1b', 6, 0, 0, 1],
    ];

    /** invoice_statuses system defaults: [key, label, color, sort, default, initial, terminal]. */
    private array $invoiceStatuses = [
        ['draft', 'Draft', '#64748b', 1, 1, 1, 0],
        ['open', 'Open', '#2563eb', 2, 0, 0, 0],
        ['partial', 'Partially Paid', '#f59e0b', 3, 0, 0, 0],
        ['paid', 'Paid', '#16a34a', 4, 0, 0, 1],
        ['uncollectible', 'Uncollectible', '#b45309', 5, 0, 0, 0],
        ['void', 'Void', '#991b1b', 6, 0, 0, 1],
        ['refunded', 'Refunded', '#7c3aed', 7, 0, 0, 1],
    ];

    /** payment_statuses system defaults: [key, label, color, sort, default, initial, terminal]. */
    private array $paymentStatuses = [
        ['pending', 'Pending', '#64748b', 1, 1, 1, 0],
        ['authorized', 'Authorized', '#2563eb', 2, 0, 0, 0],
        ['paid', 'Paid', '#16a34a', 3, 0, 0, 1],
        ['failed', 'Failed', '#dc2626', 4, 0, 0, 1],
        ['partially_refunded', 'Partially Refunded', '#f59e0b', 5, 0, 0, 0],
        ['refunded', 'Refunded', '#7c3aed', 6, 0, 0, 1],
        ['disputed', 'Disputed', '#b45309', 7, 0, 0, 0],
    ];

    /**
     * payment_gateways catalog defaults:
     * [key, name, driver, supports_recurring, supports_refund, sort].
     */
    private array $paymentGateways = [
        ['moyasar', 'Moyasar', 'moyasar', 1, 1, 1],
        ['tap', 'Tap Payments', 'tap', 1, 1, 2],
        ['hyperpay', 'HyperPay', 'hyperpay', 1, 1, 3],
        ['stripe', 'Stripe', 'stripe', 1, 1, 4],
        ['manual', 'Manual / Offline', 'manual', 0, 1, 5],
    ];

    public function up(Database $db): void
    {
        $this->createPlanFeatures($db);
        $this->createPlanPrices($db);
        $this->createSubscriptionStatuses($db);
        $this->createSubscriptionItems($db);
        $this->createSubscriptionRenewals($db);
        $this->createTrials($db);
        $this->createInvoiceStatuses($db);
        $this->createInvoices($db);
        $this->createInvoiceItems($db);
        $this->createPaymentStatuses($db);
        $this->createPaymentGateways($db);
        $this->createPaymentMethods($db);
        $this->createPayments($db);
        $this->createTransactions($db);
        $this->createGatewayEvents($db);
        $this->createCoupons($db);
        $this->createCouponRedemptions($db);
        $this->createUsageLimits($db);
        $this->createUsageRecords($db);

        $this->seedStatuses($db, 'subscription_statuses', $this->subscriptionStatuses);
        $this->seedStatuses($db, 'invoice_statuses', $this->invoiceStatuses);
        $this->seedStatuses($db, 'payment_statuses', $this->paymentStatuses);
        $this->seedPaymentGateways($db);
    }

    public function down(Database $db): void
    {
        foreach ([
            'usage_records',
            'usage_limits',
            'coupon_redemptions',
            'coupons',
            'gateway_events',
            'transactions',
            'payments',
            'payment_methods',
            'payment_gateways',
            'payment_statuses',
            'invoice_items',
            'invoices',
            'invoice_statuses',
            'trials',
            'subscription_renewals',
            'subscription_items',
            'subscription_statuses',
            'plan_prices',
            'plan_features',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // -----------------------------------------------------------------
    // Plan catalog
    // -----------------------------------------------------------------

    private function createPlanFeatures(Database $db): void
    {
        if ($this->hasTable($db, 'plan_features')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `plan_features` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `plan_id` BIGINT UNSIGNED NOT NULL,
                `feature_key` VARCHAR(80) NOT NULL,
                `label` VARCHAR(150) NULL,
                `value_type` VARCHAR(20) NOT NULL DEFAULT 'boolean',
                `value` VARCHAR(255) NULL,
                `is_highlighted` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `plan_features_uuid_unique` (`uuid`),
                UNIQUE KEY `plan_features_plan_feature_unique` (`plan_id`, `feature_key`),
                KEY `plan_features_plan_id_index` (`plan_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPlanPrices(Database $db): void
    {
        if ($this->hasTable($db, 'plan_prices')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `plan_prices` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `plan_id` BIGINT UNSIGNED NOT NULL,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `interval` VARCHAR(20) NOT NULL DEFAULT 'month',
                `interval_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `trial_days` INT NOT NULL DEFAULT 0,
                `gateway_price_ref` VARCHAR(191) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `plan_prices_uuid_unique` (`uuid`),
                UNIQUE KEY `plan_prices_plan_currency_interval_unique` (`plan_id`, `currency_id`, `interval`, `interval_count`),
                KEY `plan_prices_plan_id_index` (`plan_id`),
                KEY `plan_prices_currency_id_index` (`currency_id`),
                KEY `plan_prices_is_active_index` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Subscription children + statuses
    // -----------------------------------------------------------------

    private function createSubscriptionStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'subscription_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `subscription_statuses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `subscription_statuses_uuid_unique` (`uuid`),
                UNIQUE KEY `subscription_statuses_workspace_key_unique` (`workspace_id`, `key`),
                KEY `subscription_statuses_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createSubscriptionItems(Database $db): void
    {
        if ($this->hasTable($db, 'subscription_items')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `subscription_items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NOT NULL,
                `plan_price_id` BIGINT UNSIGNED NULL,
                `feature_key` VARCHAR(80) NULL,
                `description` VARCHAR(255) NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                `unit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `is_metered` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `subscription_items_uuid_unique` (`uuid`),
                KEY `subscription_items_workspace_id_index` (`workspace_id`),
                KEY `subscription_items_subscription_id_index` (`subscription_id`),
                KEY `subscription_items_plan_price_id_index` (`plan_price_id`),
                KEY `subscription_items_currency_id_index` (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createSubscriptionRenewals(Database $db): void
    {
        if ($this->hasTable($db, 'subscription_renewals')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `subscription_renewals` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NOT NULL,
                `invoice_id` BIGINT UNSIGNED NULL,
                `period_start` TIMESTAMP NULL DEFAULT NULL,
                `period_end` TIMESTAMP NULL DEFAULT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `outcome` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `attempt` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `failure_reason` VARCHAR(255) NULL,
                `renewed_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `subscription_renewals_uuid_unique` (`uuid`),
                KEY `subscription_renewals_workspace_id_index` (`workspace_id`),
                KEY `subscription_renewals_subscription_id_index` (`subscription_id`),
                KEY `subscription_renewals_invoice_id_index` (`invoice_id`),
                KEY `subscription_renewals_currency_id_index` (`currency_id`),
                KEY `subscription_renewals_workspace_created_index` (`workspace_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createTrials(Database $db): void
    {
        if ($this->hasTable($db, 'trials')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `trials` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `plan_id` BIGINT UNSIGNED NULL,
                `starts_at` TIMESTAMP NULL DEFAULT NULL,
                `ends_at` TIMESTAMP NULL DEFAULT NULL,
                `converted_at` TIMESTAMP NULL DEFAULT NULL,
                `converted_subscription_id` BIGINT UNSIGNED NULL,
                `source` VARCHAR(60) NULL,
                `is_extended` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `trials_uuid_unique` (`uuid`),
                KEY `trials_workspace_id_index` (`workspace_id`),
                KEY `trials_subscription_id_index` (`subscription_id`),
                KEY `trials_plan_id_index` (`plan_id`),
                KEY `trials_converted_subscription_id_index` (`converted_subscription_id`),
                KEY `trials_ends_at_index` (`ends_at`),
                KEY `trials_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Invoices + statuses
    // -----------------------------------------------------------------

    private function createInvoiceStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'invoice_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `invoice_statuses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `invoice_statuses_uuid_unique` (`uuid`),
                UNIQUE KEY `invoice_statuses_workspace_key_unique` (`workspace_id`, `key`),
                KEY `invoice_statuses_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createInvoices(Database $db): void
    {
        if ($this->hasTable($db, 'invoices')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `invoices` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `invoice_status_id` BIGINT UNSIGNED NOT NULL,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `coupon_id` BIGINT UNSIGNED NULL,
                `number` VARCHAR(40) NOT NULL,
                `subtotal_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `amount_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `tax_rate` DECIMAL(5,2) NULL,
                `seller_vat_number` VARCHAR(50) NULL,
                `buyer_vat_number` VARCHAR(50) NULL,
                `billing_name` VARCHAR(150) NULL,
                `billing_address` JSON NULL,
                `issued_at` TIMESTAMP NULL DEFAULT NULL,
                `due_at` TIMESTAMP NULL DEFAULT NULL,
                `paid_at` TIMESTAMP NULL DEFAULT NULL,
                `voided_at` TIMESTAMP NULL DEFAULT NULL,
                `notes` VARCHAR(500) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `invoices_uuid_unique` (`uuid`),
                UNIQUE KEY `invoices_workspace_number_unique` (`workspace_id`, `number`),
                KEY `invoices_workspace_id_index` (`workspace_id`),
                KEY `invoices_subscription_id_index` (`subscription_id`),
                KEY `invoices_status_id_index` (`invoice_status_id`),
                KEY `invoices_currency_id_index` (`currency_id`),
                KEY `invoices_coupon_id_index` (`coupon_id`),
                KEY `invoices_deleted_at_index` (`deleted_at`),
                KEY `invoices_workspace_status_index` (`workspace_id`, `invoice_status_id`),
                KEY `invoices_workspace_issued_index` (`workspace_id`, `issued_at`),
                KEY `invoices_due_at_index` (`due_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createInvoiceItems(Database $db): void
    {
        if ($this->hasTable($db, 'invoice_items')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `invoice_items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `invoice_id` BIGINT UNSIGNED NOT NULL,
                `subscription_item_id` BIGINT UNSIGNED NULL,
                `plan_price_id` BIGINT UNSIGNED NULL,
                `description` VARCHAR(255) NOT NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                `unit_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `line_subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `period_start` TIMESTAMP NULL DEFAULT NULL,
                `period_end` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `invoice_items_uuid_unique` (`uuid`),
                KEY `invoice_items_workspace_id_index` (`workspace_id`),
                KEY `invoice_items_invoice_id_index` (`invoice_id`),
                KEY `invoice_items_subscription_item_id_index` (`subscription_item_id`),
                KEY `invoice_items_plan_price_id_index` (`plan_price_id`),
                KEY `invoice_items_currency_id_index` (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Payments + statuses + gateways + ledger
    // -----------------------------------------------------------------

    private function createPaymentStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'payment_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `payment_statuses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `payment_statuses_uuid_unique` (`uuid`),
                UNIQUE KEY `payment_statuses_workspace_key_unique` (`workspace_id`, `key`),
                KEY `payment_statuses_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPaymentGateways(Database $db): void
    {
        if ($this->hasTable($db, 'payment_gateways')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `payment_gateways` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `key` VARCHAR(40) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `driver` VARCHAR(60) NOT NULL,
                `supports_recurring` TINYINT(1) NOT NULL DEFAULT 1,
                `supports_refund` TINYINT(1) NOT NULL DEFAULT 1,
                `supported_currencies` JSON NULL,
                `config_schema` JSON NULL,
                `logo` VARCHAR(255) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `payment_gateways_uuid_unique` (`uuid`),
                UNIQUE KEY `payment_gateways_key_unique` (`key`),
                KEY `payment_gateways_is_active_index` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPaymentMethods(Database $db): void
    {
        if ($this->hasTable($db, 'payment_methods')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `payment_methods` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `payment_gateway_id` BIGINT UNSIGNED NOT NULL,
                `type` VARCHAR(30) NOT NULL DEFAULT 'card',
                `gateway_token` VARCHAR(191) NULL,
                `brand` VARCHAR(40) NULL,
                `last_four` CHAR(4) NULL,
                `expiry_month` TINYINT UNSIGNED NULL,
                `expiry_year` SMALLINT UNSIGNED NULL,
                `holder_name` VARCHAR(150) NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `billing_details` JSON NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `payment_methods_uuid_unique` (`uuid`),
                UNIQUE KEY `payment_methods_gateway_token_unique` (`payment_gateway_id`, `gateway_token`),
                KEY `payment_methods_workspace_id_index` (`workspace_id`),
                KEY `payment_methods_gateway_id_index` (`payment_gateway_id`),
                KEY `payment_methods_created_by_index` (`created_by`),
                KEY `payment_methods_deleted_at_index` (`deleted_at`),
                KEY `payment_methods_workspace_default_index` (`workspace_id`, `is_default`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPayments(Database $db): void
    {
        if ($this->hasTable($db, 'payments')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `payments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `invoice_id` BIGINT UNSIGNED NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `payment_status_id` BIGINT UNSIGNED NOT NULL,
                `payment_gateway_id` BIGINT UNSIGNED NOT NULL,
                `payment_method_id` BIGINT UNSIGNED NULL,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `amount_refunded` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `gateway_payment_ref` VARCHAR(191) NULL,
                `gateway_reference` VARCHAR(191) NULL,
                `failure_code` VARCHAR(60) NULL,
                `failure_message` VARCHAR(255) NULL,
                `paid_at` TIMESTAMP NULL DEFAULT NULL,
                `refunded_at` TIMESTAMP NULL DEFAULT NULL,
                `metadata` JSON NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `payments_uuid_unique` (`uuid`),
                UNIQUE KEY `payments_gateway_ref_unique` (`payment_gateway_id`, `gateway_payment_ref`),
                KEY `payments_workspace_id_index` (`workspace_id`),
                KEY `payments_invoice_id_index` (`invoice_id`),
                KEY `payments_subscription_id_index` (`subscription_id`),
                KEY `payments_status_id_index` (`payment_status_id`),
                KEY `payments_gateway_id_index` (`payment_gateway_id`),
                KEY `payments_method_id_index` (`payment_method_id`),
                KEY `payments_currency_id_index` (`currency_id`),
                KEY `payments_created_by_index` (`created_by`),
                KEY `payments_deleted_at_index` (`deleted_at`),
                KEY `payments_workspace_status_index` (`workspace_id`, `payment_status_id`),
                KEY `payments_workspace_created_index` (`workspace_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createTransactions(Database $db): void
    {
        if ($this->hasTable($db, 'transactions')) {
            return;
        }
        // scale: partition candidate by RANGE(occurred_at) — keeps hard FKs
        // (financial integrity); immutable ledger, no updated_at/deleted_at.
        $db->unprepared(
            "CREATE TABLE `transactions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `payment_id` BIGINT UNSIGNED NULL,
                `invoice_id` BIGINT UNSIGNED NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `payment_gateway_id` BIGINT UNSIGNED NULL,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `type` VARCHAR(40) NOT NULL,
                `direction` VARCHAR(10) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `balance_after` DECIMAL(12,2) NULL,
                `gateway_txn_ref` VARCHAR(191) NULL,
                `parent_transaction_id` BIGINT UNSIGNED NULL,
                `description` VARCHAR(255) NULL,
                `metadata` JSON NULL,
                `occurred_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `transactions_uuid_unique` (`uuid`),
                UNIQUE KEY `transactions_gateway_txn_unique` (`payment_gateway_id`, `gateway_txn_ref`),
                KEY `transactions_workspace_id_index` (`workspace_id`),
                KEY `transactions_payment_id_index` (`payment_id`),
                KEY `transactions_invoice_id_index` (`invoice_id`),
                KEY `transactions_subscription_id_index` (`subscription_id`),
                KEY `transactions_gateway_id_index` (`payment_gateway_id`),
                KEY `transactions_currency_id_index` (`currency_id`),
                KEY `transactions_parent_id_index` (`parent_transaction_id`),
                KEY `transactions_workspace_occurred_index` (`workspace_id`, `occurred_at`),
                KEY `transactions_workspace_type_index` (`workspace_id`, `type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createGatewayEvents(Database $db): void
    {
        if ($this->hasTable($db, 'gateway_events')) {
            return;
        }
        // scale: partition candidate by RANGE(received_at) — high-volume webhook
        // log; uuid omitted for write throughput (Bible §1).
        $db->unprepared(
            "CREATE TABLE `gateway_events` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NULL,
                `payment_gateway_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `payment_id` BIGINT UNSIGNED NULL,
                `external_event_id` VARCHAR(191) NOT NULL,
                `event_type` VARCHAR(80) NOT NULL,
                `payload` JSON NOT NULL,
                `signature` VARCHAR(255) NULL,
                `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
                `processed_at` TIMESTAMP NULL DEFAULT NULL,
                `processing_error` VARCHAR(500) NULL,
                `received_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `gateway_events_external_unique` (`payment_gateway_id`, `external_event_id`),
                KEY `gateway_events_gateway_id_index` (`payment_gateway_id`),
                KEY `gateway_events_workspace_id_index` (`workspace_id`),
                KEY `gateway_events_payment_id_index` (`payment_id`),
                KEY `gateway_events_event_type_index` (`event_type`),
                KEY `gateway_events_processed_at_index` (`processed_at`),
                KEY `gateway_events_received_at_index` (`received_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Coupons
    // -----------------------------------------------------------------

    private function createCoupons(Database $db): void
    {
        if ($this->hasTable($db, 'coupons')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `coupons` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(60) NOT NULL,
                `name` VARCHAR(150) NULL,
                `discount_type` VARCHAR(20) NOT NULL DEFAULT 'percent',
                `percent_off` DECIMAL(5,2) NULL,
                `amount_off` DECIMAL(12,2) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `duration` VARCHAR(20) NOT NULL DEFAULT 'once',
                `duration_in_months` SMALLINT UNSIGNED NULL,
                `max_redemptions` INT UNSIGNED NULL,
                `max_redemptions_per_workspace` INT UNSIGNED NULL DEFAULT 1,
                `redeemed_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `applies_to_plan_id` BIGINT UNSIGNED NULL,
                `min_amount` DECIMAL(12,2) NULL,
                `starts_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `coupons_uuid_unique` (`uuid`),
                UNIQUE KEY `coupons_workspace_code_unique` (`workspace_id`, `code`),
                KEY `coupons_workspace_id_index` (`workspace_id`),
                KEY `coupons_currency_id_index` (`currency_id`),
                KEY `coupons_applies_to_plan_id_index` (`applies_to_plan_id`),
                KEY `coupons_created_by_index` (`created_by`),
                KEY `coupons_is_active_index` (`is_active`),
                KEY `coupons_expires_at_index` (`expires_at`),
                KEY `coupons_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createCouponRedemptions(Database $db): void
    {
        if ($this->hasTable($db, 'coupon_redemptions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `coupon_redemptions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `coupon_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `invoice_id` BIGINT UNSIGNED NULL,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `redeemed_by` BIGINT UNSIGNED NULL,
                `redeemed_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `coupon_redemptions_uuid_unique` (`uuid`),
                KEY `coupon_redemptions_workspace_id_index` (`workspace_id`),
                KEY `coupon_redemptions_coupon_id_index` (`coupon_id`),
                KEY `coupon_redemptions_subscription_id_index` (`subscription_id`),
                KEY `coupon_redemptions_invoice_id_index` (`invoice_id`),
                KEY `coupon_redemptions_currency_id_index` (`currency_id`),
                KEY `coupon_redemptions_redeemed_by_index` (`redeemed_by`),
                KEY `coupon_redemptions_coupon_workspace_index` (`coupon_id`, `workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Metered usage
    // -----------------------------------------------------------------

    private function createUsageLimits(Database $db): void
    {
        if ($this->hasTable($db, 'usage_limits')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `usage_limits` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `plan_id` BIGINT UNSIGNED NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `metric_key` VARCHAR(80) NOT NULL,
                `limit_value` BIGINT NULL,
                `period` VARCHAR(20) NOT NULL DEFAULT 'month',
                `overage_allowed` TINYINT(1) NOT NULL DEFAULT 0,
                `overage_unit_amount` DECIMAL(12,4) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `usage_limits_uuid_unique` (`uuid`),
                UNIQUE KEY `usage_limits_scope_metric_unique` (`plan_id`, `workspace_id`, `metric_key`, `period`),
                KEY `usage_limits_plan_id_index` (`plan_id`),
                KEY `usage_limits_workspace_id_index` (`workspace_id`),
                KEY `usage_limits_subscription_id_index` (`subscription_id`),
                KEY `usage_limits_currency_id_index` (`currency_id`),
                KEY `usage_limits_workspace_metric_index` (`workspace_id`, `metric_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createUsageRecords(Database $db): void
    {
        if ($this->hasTable($db, 'usage_records')) {
            return;
        }
        // scale: partition candidate by RANGE(recorded_at) — high-volume metered
        // events; uuid optional for write throughput (Bible §1).
        $db->unprepared(
            "CREATE TABLE `usage_records` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `subscription_id` BIGINT UNSIGNED NULL,
                `usage_limit_id` BIGINT UNSIGNED NULL,
                `metric_key` VARCHAR(80) NOT NULL,
                `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                `unit_amount` DECIMAL(12,4) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `reference_type` VARCHAR(60) NULL,
                `reference_id` BIGINT UNSIGNED NULL,
                `period_key` CHAR(7) NULL,
                `recorded_at` TIMESTAMP NULL DEFAULT NULL,
                `invoiced_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `usage_records_uuid_unique` (`uuid`),
                KEY `usage_records_workspace_id_index` (`workspace_id`),
                KEY `usage_records_subscription_id_index` (`subscription_id`),
                KEY `usage_records_usage_limit_id_index` (`usage_limit_id`),
                KEY `usage_records_currency_id_index` (`currency_id`),
                KEY `usage_records_workspace_metric_period_index` (`workspace_id`, `metric_key`, `period_key`),
                KEY `usage_records_recorded_at_index` (`recorded_at`),
                KEY `usage_records_reference_index` (`reference_type`, `reference_id`),
                KEY `usage_records_invoiced_at_index` (`invoiced_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Seeding (system scope: workspace_id = NULL, is_system = 1)
    // -----------------------------------------------------------------

    /**
     * Seed system-default rows for a config-driven status table.
     *
     * @param array<int, array{0:string,1:string,2:string,3:int,4:int,5:int,6:int}> $rows
     */
    private function seedStatuses(Database $db, string $table, array $rows): void
    {
        if (! $this->hasTable($db, $table)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($rows as [$key, $label, $color, $sort, $isDefault, $isInitial, $isTerminal]) {
            $exists = $db->table($table)
                ->whereNull('workspace_id')
                ->where('key', '=', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            $db->table($table)->insert([
                'uuid'        => $this->uuid($db),
                'workspace_id' => null,
                'key'         => $key,
                'label'       => $label,
                'color'       => $color,
                'sort_order'  => $sort,
                'is_default'  => $isDefault,
                'is_initial'  => $isInitial,
                'is_terminal' => $isTerminal,
                'is_system'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    private function seedPaymentGateways(Database $db): void
    {
        if (! $this->hasTable($db, 'payment_gateways')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->paymentGateways as [$key, $name, $driver, $recurring, $refund, $sort]) {
            if ($db->table('payment_gateways')->where('key', '=', $key)->exists()) {
                continue;
            }
            $db->table('payment_gateways')->insert([
                'uuid'               => $this->uuid($db),
                'key'                => $key,
                'name'               => $name,
                'driver'             => $driver,
                'supports_recurring' => $recurring,
                'supports_refund'    => $refund,
                'is_active'          => 1,
                'sort_order'         => $sort,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
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
