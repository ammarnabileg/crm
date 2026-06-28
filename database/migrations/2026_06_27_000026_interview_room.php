<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * The conversational AI interview room (candidate spec, page 2): a turn-by-turn
 * transcript and the interview's start time, so a candidate can leave and resume
 * within the time window.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasTable('interview_messages')) {
            $schema->create('interview_messages', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('interview_id');
                $t->string('role', 16);            // ai | candidate | system
                $t->text('content');
                $t->integer('position');
                $t->datetime('created_at');
                $t->index(['interview_id', 'position'], 'iv_msg_order_idx');
                $t->index(['workspace_id'], 'iv_msg_ws_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('interview_id', 'interviews', 'id', 'CASCADE');
            });
        }

        if (! $schema->hasColumn('interviews', 'started_at')) {
            $schema->raw('ALTER TABLE interviews ADD COLUMN started_at DATETIME NULL AFTER scheduled_at');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasColumn('interviews', 'started_at')) {
            $schema->raw('ALTER TABLE interviews DROP COLUMN started_at');
        }
        $schema->dropIfExists('interview_messages');
    }
};
