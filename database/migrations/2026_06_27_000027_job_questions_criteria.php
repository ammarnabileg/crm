<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Per-job question bank (recruitment spec #4) and evaluation criteria / rubric
 * (spec #2). Both are workspace DATA owned by the job, feeding the AI interview
 * room and the human evaluation respectively.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasTable('job_questions')) {
            $schema->create('job_questions', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('job_id');
                $t->text('text');
                $t->integer('position')->default(0);
                $t->timestamps();
                $t->index(['job_id', 'position'], 'job_q_order_idx');
                $t->index(['workspace_id'], 'job_q_ws_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
            });
        }

        if (! $schema->hasTable('job_criteria')) {
            $schema->create('job_criteria', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('job_id');
                $t->string('label');
                $t->integer('weight')->default(10);
                $t->integer('position')->default(0);
                $t->timestamps();
                $t->index(['job_id', 'position'], 'job_c_order_idx');
                $t->index(['workspace_id'], 'job_c_ws_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
            });
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('job_criteria');
        $schema->dropIfExists('job_questions');
    }
};
