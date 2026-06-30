<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Learning → real LMS: certificates (issued on completion), prerequisites
 * (a program can require others first) and learning paths (an ordered sequence of
 * programs). All workspace-scoped, FK-constrained, ULID PKs.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Certificate issued when a learner completes a program.
        $schema->createIfNotExists('learning_certificates', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('user_id');
            $t->ulid('enrollment_id')->nullable();
            $t->string('serial', 32);
            $t->string('title');
            $t->integer('percent')->default(100);
            $t->datetime('issued_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->unique(['program_id', 'user_id'], 'learning_cert_program_user_uq');
            $t->unique('serial', 'learning_cert_serial_uq');
            $t->index(['workspace_id', 'user_id'], 'learning_cert_ws_user_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        // A program may require other programs be completed first.
        $schema->createIfNotExists('learning_prerequisites', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('prerequisite_program_id');
            $t->datetime('created_at')->nullable();
            $t->unique(['program_id', 'prerequisite_program_id'], 'learning_prereq_uq');
            $t->index('prerequisite_program_id', 'learning_prereq_pre_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('prerequisite_program_id', 'learning_programs', 'id', 'CASCADE');
        });

        // A learning path: an ordered sequence of programs (curriculum/track).
        $schema->createIfNotExists('learning_paths', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('title');
            $t->string('slug', 160);
            $t->string('description', 1000)->nullable();
            $t->string('status', 16)->default('draft');   // draft|published|archived
            $t->ulid('created_by');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['workspace_id', 'slug'], 'learning_paths_ws_slug_uq');
            $t->index(['workspace_id', 'status'], 'learning_paths_ws_status_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('created_by', 'users', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_path_programs', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('path_id');
            $t->ulid('program_id');
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->unique(['path_id', 'program_id'], 'learning_path_program_uq');
            $t->index(['path_id', 'position'], 'learning_path_program_pos_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('path_id', 'learning_paths', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('learning_path_programs');
        $schema->dropIfExists('learning_paths');
        $schema->dropIfExists('learning_prerequisites');
        $schema->dropIfExists('learning_certificates');
    }
};
