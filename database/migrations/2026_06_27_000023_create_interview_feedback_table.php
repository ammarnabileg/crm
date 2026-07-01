<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Candidate feedback on the AI interview experience (recruitment spec #17). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('interview_feedback', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('interview_id');
            $t->integer('rating');            // 1..5
            $t->text('comment')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique('interview_id', 'interview_feedback_interview_uq'); // one per interview
            $t->index(['workspace_id'], 'interview_feedback_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('interview_id', 'interviews', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('interview_feedback');
    }
};
