<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Interviews — AI + human, workspace-scoped, advisory (docs/FEATURE_SPECIFICATIONS). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('interviews', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('application_id');
            $t->ulid('candidate_user_id');         // denormalized for profile queries
            $t->ulid('job_id');
            $t->string('type', 16)->default('human');   // ai | human
            $t->string('status', 16)->default('scheduled'); // scheduled | completed | canceled
            $t->string('mode', 16)->nullable();          // video | onsite | phone (human)
            $t->ulid('interviewer_user_id')->nullable(); // human interviewer
            $t->datetime('scheduled_at')->nullable();
            $t->text('transcript')->nullable();          // AI interview transcript
            $t->text('summary')->nullable();
            $t->integer('score')->nullable();            // 0..100, advisory
            $t->string('recommendation', 16)->nullable(); // advance | hold | reject
            $t->string('ai_provider', 64)->nullable();
            $t->ulid('created_by')->nullable();
            $t->datetime('completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'candidate_user_id'], 'interviews_ws_candidate_idx');
            $t->index(['application_id'], 'interviews_application_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('application_id', 'applications', 'id', 'CASCADE');
            $t->foreign('candidate_user_id', 'users', 'id', 'CASCADE');
            $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
            $t->foreign('interviewer_user_id', 'users', 'id', 'SET NULL');
            $t->foreign('created_by', 'users', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('interviews');
    }
};
