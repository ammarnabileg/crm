<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * The composed monthly plan per workspace (docs/WALLET_AND_BILLING.md §4–§7):
 * billable seats + enabled features, paid from the wallet, 1-month term, auto-
 * renew, lockable. Kept separate from legacy `subscriptions` to avoid entangling
 * its trial/grace state machine (ADR 0002).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // One composed plan per workspace (mutated across its monthly lifecycle).
        $schema->create('workspace_plans', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('status', 16)->default('active'); // active | locked | canceled
            $t->integer('seats_paid')->default(0);       // billable seats funded this month (excludes owner)
            $t->integer('monthly_cost_cents')->default(0); // snapshot: seats + features
            $t->datetime('period_start')->nullable();
            $t->datetime('period_end')->nullable();       // = period_start + 1 month
            $t->boolean('auto_renew')->default(true);
            $t->datetime('locked_at')->nullable();
            $t->timestamps();
            $t->unique('workspace_id', 'workspace_plans_ws_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        // Features included in the base plan (price snapshot at composition time).
        $schema->create('workspace_plan_features', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('plan_id');
            $t->string('feature_key', 64);
            $t->integer('price_cents')->default(0);
            $t->datetime('created_at')->nullable();
            $t->unique(['plan_id', 'feature_key'], 'ws_plan_feat_uq');
            $t->index('workspace_id', 'ws_plan_feat_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('plan_id', 'workspace_plans', 'id', 'CASCADE');
        });

        // Mid-term add-ons (extra features or seats); expire with the plan.
        $schema->create('workspace_plan_addons', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('plan_id');
            $t->string('kind', 16);                  // feature | seat
            $t->string('ref', 64)->nullable();       // feature key (for kind=feature)
            $t->integer('price_cents')->default(0);
            $t->datetime('expires_at')->nullable();  // = plan.period_end
            $t->ulid('actor_user_id')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'plan_id'], 'ws_plan_addon_ws_plan_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('plan_id', 'workspace_plans', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['workspace_plan_addons', 'workspace_plan_features', 'workspace_plans'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
