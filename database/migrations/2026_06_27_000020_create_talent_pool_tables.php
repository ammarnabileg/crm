<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Talent Pool — saved candidate lists for future roles (recruitment spec #14). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('talent_pools', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('description')->nullable();
            $t->ulid('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id'], 'talent_pools_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('talent_pool_members', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('pool_id');
            $t->ulid('candidate_user_id');
            $t->string('note')->nullable();
            $t->ulid('added_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique(['pool_id', 'candidate_user_id'], 'talent_member_unique');
            $t->index(['workspace_id', 'candidate_user_id'], 'talent_member_candidate_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('pool_id', 'talent_pools', 'id', 'CASCADE');
            $t->foreign('candidate_user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['talent_pool_members', 'talent_pools'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
