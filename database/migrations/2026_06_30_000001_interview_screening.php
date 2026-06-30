<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Per-job AI-screening controls + application availability.
 *
 * - jobs.ai_screening_enabled: workspace staff turn the AI screening interview
 *   on/off per job (off = applications are accepted without an AI interview).
 * - jobs.screening_keywords: optional HR keywords; a deterministic (no-AI)
 *   pre-screen matches the applicant's words against these to decide whether to
 *   offer the AI interview, so AI credits are not spent when there is no match.
 * - applications.available_from: the candidate's "when can you start" answer,
 *   captured at apply time. (jobs.deadline_at already exists for the deadline.)
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasColumn('jobs', 'ai_screening_enabled')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN ai_screening_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER status');
        }
        if (! $schema->hasColumn('jobs', 'screening_keywords')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN screening_keywords TEXT NULL AFTER ai_screening_enabled');
        }
        if (! $schema->hasColumn('applications', 'available_from')) {
            $schema->raw('ALTER TABLE applications ADD COLUMN available_from VARCHAR(255) NULL AFTER cover_note');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach ([['jobs', 'ai_screening_enabled'], ['jobs', 'screening_keywords'], ['applications', 'available_from']] as $pair) {
            [$table, $col] = $pair;
            if ($schema->hasColumn($table, $col)) {
                $schema->raw("ALTER TABLE {$table} DROP COLUMN {$col}");
            }
        }
    }
};
