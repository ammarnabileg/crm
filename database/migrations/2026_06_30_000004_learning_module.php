<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Learning module — workspace-owned learning, training & onboarding programs
 * (docs/LEARNING_PROGRAMS.md). A program is a tree:
 *
 *   program → sections → items (lesson|video|document|link|task|todo_list|quiz|note)
 *
 * plus collaboration (editors, versions), assignment (user/role/department/team),
 * enrollment + per-item progress, polymorphic comments (with mentions) and
 * attachments, manager/self todos with status history, a per-entity activity
 * timeline, and a quiz scaffold (architecture only for now).
 *
 * Everything is workspace-scoped (workspace_id + FK) for absolute tenant
 * isolation. ULID PKs and the house FK style (every <entity>_id constrained).
 * `visibility` on the program is the seam for future cross-workspace sharing.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // --- Program (the root aggregate) -------------------------------------
        $schema->createIfNotExists('learning_programs', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('title');
            $t->string('slug', 160);
            $t->string('summary', 500)->nullable();
            $t->longText('description')->nullable();
            $t->string('cover_path', 1024)->nullable();
            $t->string('category', 80)->nullable();
            $t->string('difficulty', 16)->default('beginner');   // beginner|intermediate|advanced
            $t->integer('estimated_minutes')->default(0);
            $t->string('status', 16)->default('draft');           // draft|published|archived
            $t->string('visibility', 16)->default('workspace');   // workspace|shared (future cross-workspace)
            $t->integer('version')->default(1);
            $t->string('completion_rule', 24)->default('required_items'); // all_items|required_items|percentage
            $t->integer('completion_threshold')->default(100);    // for the percentage rule
            $t->ulid('created_by');
            $t->datetime('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['workspace_id', 'slug'], 'learning_programs_ws_slug_uq');
            $t->index(['workspace_id', 'status'], 'learning_programs_ws_status_idx');
            $t->index(['workspace_id', 'category'], 'learning_programs_ws_cat_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('created_by', 'users', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_program_tags', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->string('tag', 60);
            $t->datetime('created_at')->nullable();
            $t->unique(['program_id', 'tag'], 'learning_tags_program_tag_uq');
            $t->index(['workspace_id', 'tag'], 'learning_tags_ws_tag_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
        });

        // --- Structure: sections + items --------------------------------------
        $schema->createIfNotExists('learning_sections', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->string('title');
            $t->string('description', 1000)->nullable();
            $t->integer('position')->default(0);
            $t->boolean('is_required')->default(1);
            $t->timestamps();
            $t->index(['program_id', 'position'], 'learning_sections_program_pos_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_items', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('section_id');
            $t->string('type', 24);   // lesson|video|document|link|task|todo_list|quiz|note
            $t->string('title');
            $t->longText('body')->nullable();          // lesson/note rich text
            $t->string('url', 1024)->nullable();        // link/video
            $t->ulid('file_id')->nullable();            // document
            $t->integer('duration_minutes')->default(0);
            $t->boolean('is_required')->default(1);
            $t->integer('position')->default(0);
            $t->timestamps();
            $t->index(['section_id', 'position'], 'learning_items_section_pos_idx');
            $t->index(['program_id', 'type'], 'learning_items_program_type_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('section_id', 'learning_sections', 'id', 'CASCADE');
            $t->foreign('file_id', 'files', 'id', 'SET NULL');
        });

        // --- Todos (self vs manager-controlled) + status history --------------
        $schema->createIfNotExists('learning_todos', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('section_id')->nullable();
            $t->ulid('item_id')->nullable();              // the todo_list item it belongs to
            $t->string('title');
            $t->string('description', 2000)->nullable();
            $t->string('completion_mode', 16)->default('self');   // self|manager
            $t->string('priority', 12)->default('normal');        // low|normal|high|urgent
            $t->string('status', 16)->default('open');            // open|in_progress|done|blocked
            $t->datetime('due_date')->nullable();
            $t->ulid('assignee_user_id')->nullable();
            $t->ulid('created_by');
            $t->datetime('completed_at')->nullable();
            $t->ulid('completed_by')->nullable();
            $t->integer('position')->default(0);
            $t->timestamps();
            $t->index(['workspace_id', 'assignee_user_id', 'status'], 'learning_todos_assignee_idx');
            $t->index(['program_id', 'item_id'], 'learning_todos_program_item_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('assignee_user_id', 'users', 'id', 'SET NULL');
            $t->foreign('created_by', 'users', 'id', 'CASCADE');
            $t->foreign('completed_by', 'users', 'id', 'SET NULL');
        });

        $schema->createIfNotExists('learning_todo_status_history', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('todo_id');
            $t->string('from_status', 16)->nullable();
            $t->string('to_status', 16);
            $t->string('note', 500)->nullable();
            $t->ulid('changed_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('todo_id', 'learning_todo_history_todo_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('todo_id', 'learning_todos', 'id', 'CASCADE');
            $t->foreign('changed_by', 'users', 'id', 'SET NULL');
        });

        // --- Comments (polymorphic) + mentions --------------------------------
        $schema->createIfNotExists('learning_comments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->string('entity_type', 24);   // program|section|item|todo
            $t->ulid('entity_id');
            $t->ulid('parent_id')->nullable();   // replies
            $t->ulid('author_user_id');
            $t->longText('body');
            $t->datetime('edited_at')->nullable();
            $t->datetime('deleted_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'entity_type', 'entity_id'], 'learning_comments_entity_idx');
            $t->index('parent_id', 'learning_comments_parent_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('author_user_id', 'users', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_comment_mentions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('comment_id');
            $t->ulid('mentioned_user_id');
            $t->datetime('created_at')->nullable();
            $t->unique(['comment_id', 'mentioned_user_id'], 'learning_mentions_uq');
            $t->index('mentioned_user_id', 'learning_mentions_user_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('comment_id', 'learning_comments', 'id', 'CASCADE');
            $t->foreign('mentioned_user_id', 'users', 'id', 'CASCADE');
        });

        // --- Attachments (polymorphic: item|todo|comment) ---------------------
        $schema->createIfNotExists('learning_attachments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('entity_type', 24);   // item|todo|comment
            $t->ulid('entity_id');
            $t->ulid('file_id');
            $t->ulid('uploaded_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'entity_type', 'entity_id'], 'learning_attachments_entity_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('file_id', 'files', 'id', 'CASCADE');
            $t->foreign('uploaded_by', 'users', 'id', 'SET NULL');
        });

        // --- Assignment (program → user|role|department|team) -----------------
        $schema->createIfNotExists('learning_assignments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->string('assignee_type', 16);   // user|role|department|team
            $t->ulid('assignee_id');
            $t->datetime('due_date')->nullable();
            $t->ulid('assigned_by');
            $t->datetime('created_at')->nullable();
            $t->unique(['program_id', 'assignee_type', 'assignee_id'], 'learning_assignments_uq');
            $t->index(['workspace_id', 'assignee_type', 'assignee_id'], 'learning_assignments_assignee_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('assigned_by', 'users', 'id', 'CASCADE');
        });

        // --- Enrollment + per-item progress -----------------------------------
        $schema->createIfNotExists('learning_enrollments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('user_id');
            $t->ulid('assignment_id')->nullable();
            $t->string('status', 16)->default('not_started');   // not_started|in_progress|completed
            $t->integer('progress_percent')->default(0);
            $t->datetime('started_at')->nullable();
            $t->datetime('completed_at')->nullable();
            $t->datetime('due_date')->nullable();
            $t->timestamps();
            $t->unique(['program_id', 'user_id'], 'learning_enrollments_uq');
            $t->index(['workspace_id', 'user_id', 'status'], 'learning_enrollments_user_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
            $t->foreign('assignment_id', 'learning_assignments', 'id', 'SET NULL');
        });

        $schema->createIfNotExists('learning_item_progress', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('enrollment_id');
            $t->ulid('item_id');
            $t->string('status', 16)->default('not_started');   // not_started|in_progress|completed
            $t->datetime('completed_at')->nullable();
            $t->ulid('completed_by')->nullable();
            $t->timestamps();
            $t->unique(['enrollment_id', 'item_id'], 'learning_item_progress_uq');
            $t->index('enrollment_id', 'learning_item_progress_enrollment_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('enrollment_id', 'learning_enrollments', 'id', 'CASCADE');
            $t->foreign('item_id', 'learning_items', 'id', 'CASCADE');
        });

        // --- Collaboration: editors + version history -------------------------
        $schema->createIfNotExists('learning_program_editors', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('user_id');
            $t->string('role', 16)->default('editor');   // owner|editor|viewer
            $t->datetime('created_at')->nullable();
            $t->unique(['program_id', 'user_id'], 'learning_editors_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_program_versions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->integer('version');
            $t->string('label', 200)->nullable();
            $t->longText('snapshot')->nullable();   // archival JSON snapshot of the structure
            $t->ulid('created_by')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('program_id', 'learning_versions_program_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('created_by', 'users', 'id', 'SET NULL');
        });

        // --- Quiz scaffold (architecture only for now) ------------------------
        $schema->createIfNotExists('learning_quiz_questions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('item_id');
            $t->string('question', 1000);
            $t->string('type', 16)->default('single');   // single|multiple|boolean
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index('item_id', 'learning_quiz_q_item_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('item_id', 'learning_items', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_quiz_options', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('question_id');
            $t->string('label', 500);
            $t->boolean('is_correct')->default(0);
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index('question_id', 'learning_quiz_opt_question_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('question_id', 'learning_quiz_questions', 'id', 'CASCADE');
        });

        // --- Per-entity activity timeline -------------------------------------
        $schema->createIfNotExists('learning_activity', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id')->nullable();
            $t->string('entity_type', 24);   // program|section|item|todo|comment|enrollment|assignment
            $t->ulid('entity_id');
            $t->ulid('actor_user_id')->nullable();
            $t->string('action', 48);
            $t->string('summary', 500)->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'entity_type', 'entity_id'], 'learning_activity_entity_idx');
            $t->index(['program_id', 'created_at'], 'learning_activity_program_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('actor_user_id', 'users', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach ([
            'learning_activity',
            'learning_quiz_options',
            'learning_quiz_questions',
            'learning_program_versions',
            'learning_program_editors',
            'learning_item_progress',
            'learning_enrollments',
            'learning_assignments',
            'learning_attachments',
            'learning_comment_mentions',
            'learning_comments',
            'learning_todo_status_history',
            'learning_todos',
            'learning_items',
            'learning_sections',
            'learning_program_tags',
            'learning_programs',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
