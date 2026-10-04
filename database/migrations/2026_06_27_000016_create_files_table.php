<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Files & CVs — workspace-scoped attachments (docs/FEATURE_SPECIFICATIONS). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('files', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('entity_type', 64)->nullable();  // e.g. candidate_profile (polymorphic)
            $t->ulid('entity_id')->nullable();
            $t->ulid('uploaded_by')->nullable();
            $t->string('original_name');
            $t->string('stored_path', 1024);             // path under storage/ (never web-served directly)
            $t->string('mime', 128)->default('application/octet-stream');
            $t->bigInteger('size_bytes')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'entity_type', 'entity_id'], 'files_ws_entity_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('uploaded_by', 'users', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('files');
    }
};
