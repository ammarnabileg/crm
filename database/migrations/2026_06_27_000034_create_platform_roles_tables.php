<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Platform-level roles & permissions (System Owner): named roles built from the
 * system.* permission catalog and assigned to users, so the platform can have
 * granular "site managers" instead of an all-or-nothing System Owner flag.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasTable('platform_roles')) {
            $schema->create('platform_roles', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->string('name');
                $t->string('description', 512)->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasTable('platform_role_permissions')) {
            $schema->create('platform_role_permissions', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('role_id');
                $t->ulid('permission_id');
                $t->datetime('created_at')->nullable();
                $t->unique(['role_id', 'permission_id'], 'platform_role_perm_uq');
                $t->foreign('role_id', 'platform_roles', 'id', 'CASCADE');
                $t->foreign('permission_id', 'permissions', 'id', 'CASCADE');
            });
        }

        if (! $schema->hasTable('platform_role_user')) {
            $schema->create('platform_role_user', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('role_id');
                $t->ulid('user_id');
                $t->datetime('created_at')->nullable();
                $t->unique(['role_id', 'user_id'], 'platform_role_user_uq');
                $t->foreign('role_id', 'platform_roles', 'id', 'CASCADE');
                $t->foreign('user_id', 'users', 'id', 'CASCADE');
            });
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('platform_role_user');
        $schema->dropIfExists('platform_role_permissions');
        $schema->dropIfExists('platform_roles');
    }
};
