<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Memberships + RBAC join tables (docs/PERMISSION_MODEL.md).
 * Permissions → Roles → Members → Workspace. Roles are data; no hard-coded roles.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('memberships', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');
            $t->string('status', 32)->default('active'); // invited|active|suspended|removed
            $t->ulid('invited_by')->nullable();
            $t->datetime('joined_at')->nullable();
            $t->datetime('last_activity_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['workspace_id', 'user_id'], 'memberships_ws_user_uq');
            $t->index('user_id');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->create('roles', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('description', 512)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['workspace_id', 'name'], 'roles_ws_name_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('role_permissions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('role_id');
            $t->ulid('permission_id');
            $t->timestamps();
            $t->unique(['role_id', 'permission_id'], 'role_permissions_uq');
            $t->foreign('role_id', 'roles', 'id', 'CASCADE');
            $t->foreign('permission_id', 'permissions', 'id', 'CASCADE');
        });

        $schema->create('membership_roles', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('membership_id');
            $t->ulid('role_id');
            $t->timestamps();
            $t->unique(['membership_id', 'role_id'], 'membership_roles_uq');
            $t->foreign('membership_id', 'memberships', 'id', 'CASCADE');
            $t->foreign('role_id', 'roles', 'id', 'CASCADE');
        });

        $schema->create('membership_permissions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('membership_id');
            $t->ulid('permission_id');
            $t->timestamps();
            $t->unique(['membership_id', 'permission_id'], 'membership_permissions_uq');
            $t->foreign('membership_id', 'memberships', 'id', 'CASCADE');
            $t->foreign('permission_id', 'permissions', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('membership_permissions');
        $schema->dropIfExists('membership_roles');
        $schema->dropIfExists('role_permissions');
        $schema->dropIfExists('roles');
        $schema->dropIfExists('memberships');
    }
};
