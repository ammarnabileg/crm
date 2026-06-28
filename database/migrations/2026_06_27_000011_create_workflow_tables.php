<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Workflow Engine (docs/WORKFLOW_ENGINE.md, ENTITY_CATALOG §7). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('workflows', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('trigger_event', 128);
            $t->json('definition');     // { steps: [ { action, params, condition? } ] }
            $t->string('status', 32)->default('published'); // draft|published
            $t->integer('version')->default(1);
            $t->boolean('enabled')->default(true);
            $t->ulid('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'trigger_event', 'enabled'], 'workflows_ws_trigger_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('workflow_executions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('workflow_id');
            $t->string('trigger_event', 128);
            $t->string('status', 32)->default('running'); // running|completed|failed
            $t->json('payload')->nullable();
            $t->integer('steps_total')->default(0);
            $t->integer('steps_done')->default(0);
            $t->text('error')->nullable();
            $t->datetime('started_at')->nullable();
            $t->datetime('finished_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'created_at'], 'workflow_exec_ws_created_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('workflow_id', 'workflows', 'id', 'CASCADE');
        });

        $schema->create('workflow_steps', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('execution_id');
            $t->integer('step_index')->default(0);
            $t->string('action', 64);
            $t->string('status', 32)->default('completed'); // completed|skipped|failed
            $t->text('output')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('execution_id');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('execution_id', 'workflow_executions', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['workflow_steps', 'workflow_executions', 'workflows'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
