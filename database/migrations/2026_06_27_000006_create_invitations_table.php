<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Workspace invitations (docs/INVITATION_SYSTEM.md, STATE_DIAGRAMS §8). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('invitations', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('email')->nullable();
            $t->string('code', 64)->nullable();
            $t->string('token_hash')->nullable();
            $t->json('role_ids')->nullable();
            $t->string('status', 32)->default('pending'); // pending|accepted|rejected|expired|cancelled
            $t->ulid('invited_by')->nullable();
            $t->datetime('expires_at')->nullable();
            $t->datetime('accepted_at')->nullable();
            $t->timestamps();
            $t->unique('code');
            $t->index(['workspace_id', 'status'], 'invitations_ws_status_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('invited_by', 'users', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('invitations');
    }
};
