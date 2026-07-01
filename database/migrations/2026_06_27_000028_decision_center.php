<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Decision Center (Sprint 3.2): application stage history (an auditable trail of
 * the 11-state workflow) and structured candidate data (education, languages,
 * skills, certificates, salary, availability) on the per-workspace profile.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasTable('application_status_history')) {
            $schema->create('application_status_history', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('application_id');
                $t->string('from_status', 32)->nullable();
                $t->string('to_status', 32);
                $t->ulid('changed_by')->nullable();
                $t->datetime('created_at');
                $t->index(['application_id', 'created_at'], 'ash_app_idx');
                $t->index(['workspace_id'], 'ash_ws_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('application_id', 'applications', 'id', 'CASCADE');
            });
        }

        // Structured candidate data (CV-derived), workspace-scoped for privacy.
        if (! $schema->hasColumn('candidate_profiles', 'details')) {
            $schema->raw('ALTER TABLE candidate_profiles ADD COLUMN details JSON NULL AFTER summary');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasColumn('candidate_profiles', 'details')) {
            $schema->raw('ALTER TABLE candidate_profiles DROP COLUMN details');
        }
        $schema->dropIfExists('application_status_history');
    }
};
