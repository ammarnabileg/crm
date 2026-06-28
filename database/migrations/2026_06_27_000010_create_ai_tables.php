<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * AI Engine data (docs/AI_ENGINE.md, ENTITY_CATALOG §6). Per-workspace settings
 * + encrypted keys, AI sessions, usage, and a prompt library.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('ai_settings', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('provider', 64)->default('echo');
            $t->string('model', 128)->nullable();
            $t->string('fallback_provider', 64)->nullable();
            $t->boolean('use_platform_key')->default(true);
            $t->integer('monthly_cost_limit_cents')->nullable();
            $t->timestamps();
            $t->unique('workspace_id');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('ai_keys', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('provider', 64);
            $t->text('encrypted_key');     // ciphertext only — never plaintext
            $t->string('key_hint', 16)->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'provider'], 'ai_keys_ws_provider_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('ai_sessions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('capability', 64);
            $t->string('provider', 64);
            $t->string('model', 128)->nullable();
            $t->string('status', 32)->default('completed'); // completed|failed
            $t->longText('prompt')->nullable();
            $t->longText('response')->nullable();
            $t->integer('input_tokens')->default(0);
            $t->integer('output_tokens')->default(0);
            $t->integer('cost_cents')->default(0);
            $t->integer('latency_ms')->default(0);
            $t->string('fallback_from', 64)->nullable();
            $t->ulid('actor_user_id')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'created_at'], 'ai_sessions_ws_created_idx');
            $t->index(['workspace_id', 'capability'], 'ai_sessions_ws_capability_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('prompt_templates', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('capability', 64);
            $t->integer('version')->default(1);
            $t->string('locale', 8)->default('en');
            $t->longText('template');
            $t->timestamps();
            $t->unique(['capability', 'version', 'locale'], 'prompt_templates_cap_ver_loc_uq');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['prompt_templates', 'ai_sessions', 'ai_keys', 'ai_settings'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
