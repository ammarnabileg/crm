<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Billing & Licensing (docs/BILLING_PLATFORM.md): plans, subscriptions, invoices. */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Platform-global plan catalog (data, not code).
        $schema->create('plans', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('code', 64);
            $t->string('name');
            $t->string('description')->nullable();
            $t->integer('price_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('interval', 16)->default('month'); // month|year|none
            $t->integer('trial_days')->default(0);
            $t->json('features');                          // ["ai","automation","integrations"]
            $t->json('limits');                            // {"members":3,"jobs":5,...}
            $t->boolean('is_public')->default(true);
            $t->integer('sort')->default(0);
            $t->timestamps();
            $t->unique('code', 'plans_code_uq');
        });

        // One subscription row per workspace (mutated across its lifecycle).
        $schema->create('subscriptions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('plan_id');
            $t->string('status', 32)->default('trialing'); // trialing|active|past_due|grace|suspended|canceled
            $t->datetime('trial_ends_at')->nullable();
            $t->datetime('current_period_start')->nullable();
            $t->datetime('current_period_end')->nullable();
            $t->datetime('grace_ends_at')->nullable();
            $t->datetime('canceled_at')->nullable();
            $t->boolean('cancel_at_period_end')->default(false);
            $t->string('provider', 32)->default('manual');
            $t->string('provider_ref')->nullable();
            $t->timestamps();
            $t->unique('workspace_id', 'subscriptions_ws_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('plan_id', 'plans', 'id', 'RESTRICT');
        });

        // Immutable invoices generated on activation/renewal.
        $schema->create('invoices', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('subscription_id');
            $t->string('number', 32);
            $t->integer('amount_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('status', 16)->default('open'); // draft|open|paid|void
            $t->datetime('period_start')->nullable();
            $t->datetime('period_end')->nullable();
            $t->json('line_items')->nullable();
            $t->string('provider', 32)->default('manual');
            $t->string('provider_ref')->nullable();
            $t->datetime('issued_at')->nullable();
            $t->datetime('paid_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique('number', 'invoices_number_uq');
            $t->index(['workspace_id', 'created_at'], 'invoices_ws_created_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('subscription_id', 'subscriptions', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['invoices', 'subscriptions', 'plans'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
