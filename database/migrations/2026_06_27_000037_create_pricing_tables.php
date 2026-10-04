<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Platform-set pricing as data (docs/WALLET_AND_BILLING.md §3). Edited only by a
 * System Owner holding `system.pricing.manage`; workspace owners never set prices.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Scalar unit prices (primarily the per-seat monthly price).
        $schema->create('pricing_catalog', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('key', 64);                 // e.g. "seat"
            $t->integer('unit_price_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique('key', 'pricing_catalog_key_uq');
        });

        // The feature catalog shown in the plan composer. Basics price at 0.
        $schema->create('billing_features', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('key', 64);                 // e.g. "automation", "integrations"
            $t->string('name');
            $t->string('description')->nullable();
            $t->string('category', 16)->default('premium'); // basic | premium
            $t->integer('price_cents')->default(0);
            $t->integer('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique('key', 'billing_features_key_uq');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['billing_features', 'pricing_catalog'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
