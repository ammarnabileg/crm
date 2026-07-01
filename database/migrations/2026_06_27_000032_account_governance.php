<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Platform account governance (System Owner): block an account from creating
 * workspaces, and give each account a plan that caps how many workspaces it may
 * run, with renewal date and granted bonus months.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasColumn('users', 'can_create_workspaces')) {
            $schema->raw('ALTER TABLE users ADD COLUMN can_create_workspaces TINYINT(1) NOT NULL DEFAULT 1');
        }

        if (! $schema->hasTable('account_plans')) {
            $schema->create('account_plans', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('user_id');                   // the account owner
                $t->ulid('plan_id')->nullable();       // null = no/free plan
                $t->string('status', 32)->default('active'); // active | suspended | canceled
                $t->datetime('started_at')->nullable();
                $t->datetime('expires_at')->nullable(); // null = never expires
                $t->integer('bonus_months')->default(0); // free months granted by the platform owner
                $t->timestamps();
                $t->unique('user_id', 'account_plans_user_uq');
                $t->foreign('user_id', 'users', 'id', 'CASCADE');
                $t->foreign('plan_id', 'plans', 'id', 'SET NULL');
            });
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('account_plans');
        if ($schema->hasColumn('users', 'can_create_workspaces')) {
            $schema->raw('ALTER TABLE users DROP COLUMN can_create_workspaces');
        }
    }
};
