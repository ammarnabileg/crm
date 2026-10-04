<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Manual candidate Timeline entries — the "mini-CRM" layer. Recruiters can log a
 * free-form activity (a call, a message, a meeting, a note, or a generic update)
 * against a candidate WITHIN one workspace, with the date it happened and the
 * account that logged it. The read-side {@see CandidateTimelineService} merges
 * these with the already-observed events (applications, stages, interviews,
 * offers, notes, files, learning). Normalised, workspace-scoped, no JSON.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('candidate_timeline_entries', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');          // the candidate
            $t->string('kind', 24)->default('update'); // update | call | message | meeting | note
            $t->string('body', 1000);
            $t->datetime('occurred_at');  // when it actually happened (recruiter-set)
            $t->ulid('created_by')->nullable(); // the account that logged it
            $t->datetime('created_at')->nullable();
            $t->softDeletes();
            $t->index(['workspace_id', 'user_id'], 'cte_ws_user_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('candidate_timeline_entries');
    }
};
