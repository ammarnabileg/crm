<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Wallet-plan invoices are not tied to a legacy `subscriptions` row, so the
 * invoice's subscription_id becomes nullable (the FK still holds for non-null
 * values). See docs/WALLET_AND_BILLING.md §10.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->raw('ALTER TABLE `invoices` MODIFY `subscription_id` CHAR(26) NULL');
    }

    public function down(SchemaBuilder $schema): void
    {
        // Best-effort revert; only valid when no NULL subscription_id rows exist.
        $schema->raw('ALTER TABLE `invoices` MODIFY `subscription_id` CHAR(26) NOT NULL');
    }
};
