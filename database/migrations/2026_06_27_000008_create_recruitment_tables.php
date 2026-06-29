<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Recruitment bounded context (docs/FEATURE_SPECIFICATIONS/Recruitment.md,
 * ENTITY_CATALOG §5, STATE_DIAGRAMS §2–§3). All workspace-scoped.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('jobs', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('title');
            $t->string('slug');
            $t->longText('description')->nullable();
            $t->string('status', 32)->default('draft'); // draft|published|paused|closed|archived
            $t->string('employment_type', 32)->nullable();
            $t->string('location')->nullable();
            $t->string('public_token', 32);
            $t->ulid('created_by');
            $t->datetime('deadline_at')->nullable();
            $t->datetime('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique('public_token');
            $t->index(['workspace_id', 'status'], 'jobs_ws_status_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('created_by', 'users', 'id', 'RESTRICT');
        });

        $schema->create('pipeline_stages', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('job_id');
            $t->string('name');
            $t->integer('position')->default(0);
            $t->string('type', 32)->default('stage'); // stage|hired|rejected
            $t->timestamps();
            $t->index(['job_id', 'position'], 'pipeline_stages_job_pos_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
        });

        $schema->create('candidate_profiles', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');
            $t->text('summary')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id'], 'candidate_profiles_ws_user_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->create('applications', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('job_id');
            $t->ulid('user_id');
            $t->ulid('candidate_profile_id')->nullable();
            $t->ulid('current_stage_id')->nullable();
            $t->string('status', 32)->default('applied');
            $t->string('source', 64)->nullable();
            $t->text('cover_note')->nullable();
            $t->datetime('applied_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['job_id', 'user_id'], 'applications_job_user_uq');
            $t->index(['workspace_id', 'status'], 'applications_ws_status_idx');
            $t->index(['workspace_id', 'current_stage_id'], 'applications_ws_stage_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('job_id', 'jobs', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
            $t->foreign('candidate_profile_id', 'candidate_profiles', 'id', 'SET NULL');
            $t->foreign('current_stage_id', 'pipeline_stages', 'id', 'SET NULL');
        });

        $schema->create('application_stage_history', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('application_id');
            $t->ulid('from_stage_id')->nullable();
            $t->ulid('to_stage_id')->nullable();
            $t->ulid('moved_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('application_id');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('application_id', 'applications', 'id', 'CASCADE');
        });

        $schema->create('tags', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('color', 16)->default('slate');
            $t->timestamps();
            $t->unique(['workspace_id', 'name'], 'tags_ws_name_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('candidate_notes', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('candidate_profile_id');
            $t->ulid('author_user_id')->nullable();
            $t->text('body');
            $t->timestamps();
            $t->softDeletes();
            $t->index('candidate_profile_id');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('candidate_profile_id', 'candidate_profiles', 'id', 'CASCADE');
        });

        $schema->create('candidate_profile_tags', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('candidate_profile_id');
            $t->ulid('tag_id');
            $t->timestamps();
            $t->unique(['candidate_profile_id', 'tag_id'], 'candidate_profile_tags_uq');
            $t->foreign('candidate_profile_id', 'candidate_profiles', 'id', 'CASCADE');
            $t->foreign('tag_id', 'tags', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['candidate_profile_tags', 'candidate_notes', 'tags', 'application_stage_history', 'applications', 'candidate_profiles', 'pipeline_stages', 'jobs'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
