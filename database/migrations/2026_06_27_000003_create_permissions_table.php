<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Global permission catalog (docs/PERMISSION_CATALOG.md). Keys, not roles. */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('permissions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('key');          // e.g. job.create, system.workspaces.manage
            $t->string('category', 64); // display-only grouping
            $t->string('description', 512)->nullable();
            $t->boolean('is_system')->default(false); // system.* vs workspace
            $t->timestamps();
            $t->unique('key');
            $t->index('category');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('permissions');
    }
};
