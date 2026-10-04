<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Append-only audit log + global system settings (docs/AUDIT_POLICY.md). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Immutable: no updated_at / deleted_at.
        $schema->create('audit_logs', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id')->nullable(); // null for system-level events
            $t->ulid('actor_user_id')->nullable();
            $t->string('action');                 // e.g. authentication.user.registered
            $t->string('entity_type', 128)->nullable();
            $t->string('entity_id', 26)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->json('changes')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'created_at'], 'audit_logs_ws_created_idx');
            $t->index(['entity_type', 'entity_id'], 'audit_logs_entity_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'SET NULL');
            $t->foreign('actor_user_id', 'users', 'id', 'SET NULL');
        });

        $schema->create('settings', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('key');
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique('key');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('settings');
        $schema->dropIfExists('audit_logs');
    }
};
