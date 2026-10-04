<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Workspace tasks (Feature 14: Dashboard "My tasks"). Lightweight hiring to-dos
 * scoped to a workspace, optionally assigned and optionally linked to an entity
 * (e.g. a candidate or application).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('tasks', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('title');
            $t->text('description')->nullable();
            $t->ulid('assignee_user_id')->nullable();
            $t->ulid('created_by')->nullable();
            $t->string('status', 16)->default('open'); // open | done
            $t->string('priority', 16)->default('normal'); // low | normal | high
            $t->datetime('due_at')->nullable();
            $t->string('entity_type', 64)->nullable();
            $t->ulid('entity_id')->nullable();
            $t->datetime('completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'status'], 'tasks_ws_status_idx');
            $t->index(['assignee_user_id', 'status'], 'tasks_assignee_status_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('assignee_user_id', 'users', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('tasks');
    }
};
