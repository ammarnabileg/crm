<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Learning quizzes — the take/grade layer on top of the quiz scaffold
 * (learning_quiz_questions / learning_quiz_options). A learner submits answers,
 * we grade deterministically (no AI) and record an attempt + its answers. The
 * quiz item carries a pass mark. Workspace-scoped, FK-constrained.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Pass mark (%) lives on the quiz item; default 70.
        if (! $schema->hasColumn('learning_items', 'pass_mark')) {
            $schema->raw('ALTER TABLE learning_items ADD COLUMN pass_mark INT NOT NULL DEFAULT 70 AFTER duration_minutes');
        }

        $schema->createIfNotExists('learning_quiz_attempts', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('program_id');
            $t->ulid('item_id');
            $t->ulid('user_id');
            $t->integer('score')->default(0);          // correct answers
            $t->integer('max_score')->default(0);       // gradable questions
            $t->integer('percent')->default(0);
            $t->boolean('passed')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'item_id', 'user_id'], 'lqa_item_user_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('program_id', 'learning_programs', 'id', 'CASCADE');
            $t->foreign('item_id', 'learning_items', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('learning_quiz_answers', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('attempt_id');
            $t->ulid('question_id');
            $t->ulid('option_id')->nullable();
            $t->boolean('is_correct')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index('attempt_id', 'lqans_attempt_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('attempt_id', 'learning_quiz_attempts', 'id', 'CASCADE');
            $t->foreign('question_id', 'learning_quiz_questions', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('learning_quiz_answers');
        $schema->dropIfExists('learning_quiz_attempts');
        if ($schema->hasColumn('learning_items', 'pass_mark')) {
            $schema->raw('ALTER TABLE learning_items DROP COLUMN pass_mark');
        }
    }
};
