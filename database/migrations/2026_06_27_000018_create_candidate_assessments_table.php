<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** AI candidate assessments — advisory, workspace-scoped (docs/AI_ENGINE.md). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('candidate_assessments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('candidate_user_id');
            $t->ulid('application_id')->nullable();
            $t->ulid('interview_id')->nullable();
            $t->string('source', 16)->default('interview'); // interview | cv
            $t->integer('fit_score')->default(0);            // 0..100 overall
            $t->string('recommendation', 16)->default('maybe'); // strong|suitable|maybe|unsuitable
            $t->text('summary')->nullable();
            $t->json('strengths')->nullable();               // list<string>
            $t->json('weaknesses')->nullable();              // list<string>
            $t->json('skills')->nullable();                  // {key:{score,confidence,evidence}}
            $t->json('behavior')->nullable();                // {disc,big_five,growth,stress,leadership}
            $t->json('red_flags')->nullable();               // list<{severity,note}>
            $t->json('cv')->nullable();                      // {match,skills,companies,gaps,notes}
            $t->string('ai_provider', 64)->nullable();
            $t->ulid('created_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'candidate_user_id'], 'assessments_ws_candidate_idx');
            $t->index(['workspace_id', 'fit_score'], 'assessments_ws_score_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('candidate_user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('candidate_assessments');
    }
};
