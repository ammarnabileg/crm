<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Named AI provider profiles (Phase B): reusable {provider, model, key} per
 * workspace with one default. Keys encrypted at rest; the customer brings their
 * own key (Constitution section 8).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('ai_provider_profiles', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('provider', 64);
            $t->string('model')->nullable();
            $t->text('encrypted_key')->nullable();
            $t->string('key_hint', 32)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->index('workspace_id', 'ai_provider_profiles_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('ai_provider_profiles');
    }
};
