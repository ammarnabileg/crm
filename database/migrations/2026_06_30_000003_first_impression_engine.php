<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * First Impression Engine — a fully rule-based, ZERO-AI gate that runs BEFORE
 * any paid AI interview, so AI credits are never spent on a candidate who is
 * clearly far from the job (docs/FIRST_IMPRESSION_ENGINE.md).
 *
 * The data is deliberately NORMALISED (no giant JSON blobs, no duplication):
 *
 *   user_resumes ............. the candidate's GLOBAL CV library (per user, reused
 *                              across every workspace — the CV is the user's, not
 *                              the workspace's). One row per uploaded CV.
 *   user_social_profiles ..... the candidate's GLOBAL social links (per user).
 *
 *   first_impression_reports . one report per (application/candidate, job). Holds
 *                              only the headline scores + decision.
 *   resume_analysis .......... the Resume Analysis Engine output (sub-scores).
 *   resume_analysis_details .. flexible normalised kind/value rows (matched/missing
 *                              skills, rule matches/failures, strengths, sections…).
 *   social_analysis .......... the Social Credibility roll-up (optional boost only).
 *   social_profiles_snapshot . one row per social source evaluated for a report.
 *   social_signals_snapshot .. normalised metric rows extracted per source.
 *
 * Per-job controls live on `jobs` (same pattern as ai_screening_enabled):
 *   jobs.first_impression_enabled ...... turn the gate on/off per job (default OFF
 *                                        so existing jobs behave exactly as before).
 *   jobs.min_first_impression_score .... the threshold (>= passes to the AI step).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // --- GLOBAL (per-user) -------------------------------------------------

        // The candidate's CV library: owned by the User, reusable in any workspace.
        $schema->createIfNotExists('user_resumes', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('user_id');
            $t->string('original_name');
            $t->string('stored_path', 500);
            $t->string('mime', 100)->nullable();
            $t->integer('size_bytes')->default(0);
            // Cached parse output so re-analysis across jobs never re-parses bytes.
            $t->longText('extracted_text')->nullable();
            $t->string('parser', 24)->nullable();
            $t->integer('parse_confidence')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['user_id', 'created_at'], 'user_resumes_user_idx');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        // The candidate's social links: owned by the User, reusable in any workspace.
        $schema->createIfNotExists('user_social_profiles', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('user_id');
            $t->string('platform', 32);       // github | linkedin | website | behance | …
            $t->string('url', 500);
            $t->string('username', 191)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'url'], 'user_social_user_url_uq');
            $t->index('user_id', 'user_social_user_idx');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        // --- WORKSPACE-SCOPED (the report + its normalised children) -----------

        $schema->createIfNotExists('first_impression_reports', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('application_id')->nullable();
            $t->ulid('candidate_user_id');
            $t->ulid('job_id');
            $t->ulid('resume_id')->nullable();
            // Headline scores (0..100). overall = first impression credibility score.
            $t->integer('overall_score')->default(0);
            $t->integer('core_score')->default(0);          // resume + job-match (the basis)
            $t->integer('resume_score')->default(0);
            $t->integer('job_match_score')->default(0);
            $t->integer('social_score')->nullable();        // NULL when no social provided
            $t->integer('social_boost')->default(0);        // signed optional boost applied
            $t->integer('threshold')->default(0);           // min score at decision time
            $t->boolean('passed')->default(0);
            $t->string('decision', 24)->default('filtered'); // passed | filtered
            $t->boolean('overridden')->default(0);
            $t->ulid('overridden_by')->nullable();
            $t->datetime('overridden_at')->nullable();
            $t->integer('confidence')->default(0);
            $t->string('engine_version', 16)->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'job_id'], 'fir_ws_job_idx');
            $t->index(['workspace_id', 'candidate_user_id'], 'fir_ws_cand_idx');
            $t->index('application_id', 'fir_app_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('resume_analysis', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('report_id');
            $t->ulid('resume_id')->nullable();
            $t->string('parser', 24)->nullable();
            $t->integer('parse_confidence')->default(0);
            $t->integer('text_length')->default(0);
            $t->integer('score')->default(0);               // resume score 0..100
            // Sub-scores (all 0..100).
            $t->integer('completeness')->default(0);
            $t->integer('experience_match')->default(0);
            $t->integer('skill_match')->default(0);
            $t->integer('education_match')->default(0);
            $t->integer('language_match')->default(0);
            $t->integer('seniority_match')->default(0);
            $t->integer('keyword_density')->default(0);
            $t->integer('employment_stability')->default(0);
            $t->integer('formatting_quality')->default(0);
            $t->integer('missing_sections_count')->default(0);
            $t->integer('confidence')->default(0);
            $t->integer('years_experience')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('report_id', 'resume_analysis_report_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('report_id', 'first_impression_reports', 'id', 'CASCADE');
        });

        // Flexible, normalised detail rows — NEVER a big JSON blob.
        $schema->createIfNotExists('resume_analysis_details', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('analysis_id');
            $t->ulid('report_id');
            // kind: skill_matched | skill_missing | section_present | section_missing
            //     | rule_match | rule_fail | strength | weakness | recommendation
            //     | keyword_hit | title | company | education | certification
            //     | language | project | publication | award | link
            $t->string('kind', 32);
            $t->string('label');
            $t->string('value')->nullable();
            $t->integer('score')->nullable();
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index(['analysis_id', 'kind'], 'rad_analysis_kind_idx');
            $t->index(['report_id', 'kind'], 'rad_report_kind_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('analysis_id', 'resume_analysis', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('social_analysis', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('report_id');
            $t->integer('score')->default(0);               // social score 0..100 (raw)
            $t->integer('boost')->default(0);               // signed boost applied to core
            $t->integer('sources_total')->default(0);
            $t->integer('sources_reachable')->default(0);
            $t->integer('signals_count')->default(0);
            $t->integer('confidence')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index('report_id', 'social_analysis_report_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('report_id', 'first_impression_reports', 'id', 'CASCADE');
        });

        $schema->createIfNotExists('social_profiles_snapshot', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('social_analysis_id');
            $t->ulid('report_id');
            $t->string('platform', 32);
            $t->string('url', 500);
            $t->boolean('fetched')->default(0);
            $t->boolean('reachable')->default(0);
            $t->integer('score')->nullable();               // per-source 0..100
            $t->string('summary', 500)->nullable();
            $t->string('error')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('social_analysis_id', 'sps_analysis_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('social_analysis_id', 'social_analysis', 'id', 'CASCADE');
        });

        // Normalised extracted metrics per social source (key/value, no JSON blob).
        $schema->createIfNotExists('social_signals_snapshot', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('snapshot_id');
            $t->string('signal_key', 64);     // public_repos | followers | stars | reputation | ssl | current_position | bio | account_age_days …
            $t->string('string_value', 500)->nullable();
            $t->bigInteger('numeric_value')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index('snapshot_id', 'sss_snapshot_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('snapshot_id', 'social_profiles_snapshot', 'id', 'CASCADE');
        });

        // --- Per-job controls (idempotent ALTERs, same pattern as screening) ---
        if (! $schema->hasColumn('jobs', 'first_impression_enabled')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN first_impression_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER screening_keywords');
        }
        if (! $schema->hasColumn('jobs', 'min_first_impression_score')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN min_first_impression_score INT NOT NULL DEFAULT 65 AFTER first_impression_enabled');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['min_first_impression_score', 'first_impression_enabled'] as $col) {
            if ($schema->hasColumn('jobs', $col)) {
                $schema->raw("ALTER TABLE jobs DROP COLUMN {$col}");
            }
        }

        // Drop children before parents (FK order).
        foreach ([
            'social_signals_snapshot',
            'social_profiles_snapshot',
            'social_analysis',
            'resume_analysis_details',
            'resume_analysis',
            'first_impression_reports',
            'user_social_profiles',
            'user_resumes',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
