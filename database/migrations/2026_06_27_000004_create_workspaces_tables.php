<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** The tenant root and its settings (docs/WORKSPACE_MODEL.md). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('workspaces', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('name');
            $t->string('slug');
            $t->ulid('owner_user_id');
            $t->string('status', 32)->default('active'); // active | archived | deleted
            $t->string('timezone', 64)->default('UTC');
            $t->string('locale', 8)->default('en');
            $t->string('currency', 8)->default('USD');
            $t->json('company')->nullable(); // optional company info lives in settings
            $t->datetime('archived_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique('slug');
            $t->foreign('owner_user_id', 'users', 'id', 'RESTRICT');
        });

        $schema->create('workspace_settings', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('key');
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'key'], 'workspace_settings_ws_key_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('workspace_settings');
        $schema->dropIfExists('workspaces');
    }
};
