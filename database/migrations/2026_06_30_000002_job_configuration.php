<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Full per-job configuration (the job is the single control surface for its
 * hiring automation). Every column is nullable or defaulted so existing jobs
 * keep their current behaviour:
 *
 * - interview_required      : the AI interview is a required step (vs optional).
 * - interview_type          : text | voice | avatar (presentation of the room).
 * - avatar_id               : the linked AI interviewer avatar (persona); NULL =
 *                             the default strong-HR persona.
 * - required_skills         : HR's must-have skills (used by skills matching).
 * - experience_min/max      : desired experience window in years.
 * - passing_score           : score at/above which the candidate auto-qualifies.
 * - auto_reject_score       : score below which the candidate is auto-rejected.
 * - auto_advance_stage_id   : pipeline stage to move qualified candidates into.
 * - interview_expiration_days: days the scheduled interview stays open.
 * - max_attempts            : how many times a candidate may take the interview.
 * - interview_duration_minutes: overrides the default room duration.
 * - questions_limit         : overrides the default question budget.
 * - interview_start_mode     : immediate | later | choice (start-now-or-later).
 *
 * (jobs.ai_screening_enabled, jobs.screening_keywords and jobs.deadline_at
 * already exist; applications.available_from holds "when can you start".)
 */
return new class extends Migration {
    /** @var list<array{0:string,1:string}> column => DDL fragment */
    private const COLUMNS = [
        ['interview_required', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER ai_screening_enabled'],
        ['interview_type', "VARCHAR(16) NOT NULL DEFAULT 'text' AFTER interview_required"],
        ['avatar_id', 'CHAR(26) NULL AFTER interview_type'],
        ['required_skills', 'TEXT NULL AFTER screening_keywords'],
        ['experience_min', 'INT NULL AFTER required_skills'],
        ['experience_max', 'INT NULL AFTER experience_min'],
        ['passing_score', 'INT NULL AFTER experience_max'],
        ['auto_reject_score', 'INT NULL AFTER passing_score'],
        ['auto_advance_stage_id', 'CHAR(26) NULL AFTER auto_reject_score'],
        ['interview_expiration_days', 'INT NULL AFTER auto_advance_stage_id'],
        ['max_attempts', 'INT NOT NULL DEFAULT 1 AFTER interview_expiration_days'],
        ['interview_duration_minutes', 'INT NULL AFTER max_attempts'],
        ['questions_limit', 'INT NULL AFTER interview_duration_minutes'],
        ['interview_start_mode', "VARCHAR(16) NOT NULL DEFAULT 'choice' AFTER questions_limit"],
    ];

    public function up(SchemaBuilder $schema): void
    {
        foreach (self::COLUMNS as [$col, $ddl]) {
            if (! $schema->hasColumn('jobs', $col)) {
                $schema->raw("ALTER TABLE jobs ADD COLUMN {$col} {$ddl}");
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (self::COLUMNS as [$col, $ddl]) {
            if ($schema->hasColumn('jobs', $col)) {
                $schema->raw("ALTER TABLE jobs DROP COLUMN {$col}");
            }
        }
    }
};
