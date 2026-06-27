<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * The single human identity (docs/USER_MODEL.md, docs/ENTITY_CATALOG.md §3).
 * System Owner is a User holding system.* permissions — no separate table.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('users', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('name');
            $t->string('email');
            $t->string('password_hash');
            $t->string('locale', 8)->default('en');
            $t->string('timezone', 64)->default('UTC');
            $t->string('status', 32)->default('active'); // active | suspended | deactivated
            $t->boolean('is_system_owner')->default(false);
            $t->datetime('email_verified_at')->nullable();
            $t->datetime('last_login_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique('email');
            $t->index('status');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('users');
    }
};
