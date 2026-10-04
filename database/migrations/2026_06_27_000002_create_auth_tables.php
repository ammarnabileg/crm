<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Authentication support tables (sessions, password resets, remember tokens). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('user_sessions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('user_id');
            $t->string('token_hash');
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 512)->nullable();
            $t->datetime('last_active_at')->nullable();
            $t->datetime('expires_at')->nullable();
            $t->timestamps();
            $t->unique('token_hash');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->create('password_resets', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('user_id');
            $t->string('token_hash');
            $t->datetime('expires_at')->nullable();
            $t->datetime('used_at')->nullable();
            $t->timestamps();
            $t->unique('token_hash');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->create('remember_tokens', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('user_id');
            $t->string('token_hash');
            $t->datetime('expires_at')->nullable();
            $t->timestamps();
            $t->unique('token_hash');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('remember_tokens');
        $schema->dropIfExists('password_resets');
        $schema->dropIfExists('user_sessions');
    }
};
