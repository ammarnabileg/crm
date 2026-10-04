<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** AI interviewer avatars/personas (recruitment spec #3). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('ai_avatars', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('persona', 32)->default('professional'); // professional | friendly | formal | casual
            $t->string('gender', 16)->nullable();
            $t->string('language', 16)->default('en');
            $t->string('image_url', 1024)->nullable();
            $t->text('style_notes')->nullable();
            $t->ulid('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id'], 'ai_avatars_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('ai_avatars');
    }
};
