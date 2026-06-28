<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Interview invitation links — tokenized, expiring, single-use (recruitment spec #5). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('interview_invitations', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('job_id');
            $t->ulid('application_id')->nullable();
            $t->ulid('interview_id')->nullable();
            $t->string('candidate_email')->nullable();
            $t->string('token', 64);
            $t->string('status', 16)->default('pending'); // pending|completed|expired|revoked
            $t->datetime('expires_at');
            $t->datetime('completed_at')->nullable();
            $t->ulid('created_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique('token', 'interview_invitations_token_uq');
            $t->index(['workspace_id', 'job_id'], 'interview_invitations_ws_job_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
            $t->foreign('application_id', 'applications', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('interview_invitations');
    }
};
