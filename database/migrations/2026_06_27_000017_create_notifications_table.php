<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Notifications — personal, per-(workspace,user) (docs/FEATURE_SPECIFICATIONS). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('notifications', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');                 // recipient
            $t->string('type', 64)->default('info');
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('link', 512)->nullable();
            $t->datetime('read_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'user_id', 'read_at'], 'notifications_ws_user_read_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('notifications');
    }
};
