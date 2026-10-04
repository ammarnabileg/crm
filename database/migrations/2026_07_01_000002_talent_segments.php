<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Smart Segments for the Talent Pool — named, saved candidate filters (a smarter
 * alternative to one-shot search). A segment is a set of rules combined with AND
 * (match_type=all) or OR (match_type=any); each rule is a single, normalised
 * criterion (skill / language / min score / last-interview recency / available /
 * status / seniority). Fully workspace-scoped (docs/RECRUITMENT.md,
 * docs/DATABASE_ARCHITECTURE.md). Normalised — no JSON.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('talent_segments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('name');
            $t->string('match_type', 8)->default('all'); // all = AND, any = OR
            $t->ulid('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id'], 'talent_segments_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->create('talent_segment_rules', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('segment_id');
            $t->string('field', 32);      // skill | language | min_score | last_interview_months | available | status | seniority
            $t->string('operator', 16);   // like | gte | within_months | is_true | eq
            $t->string('value', 255)->nullable();
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'segment_id'], 'tsr_ws_segment_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('segment_id', 'talent_segments', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['talent_segment_rules', 'talent_segments'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
