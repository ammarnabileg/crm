<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;
use HaHireAI\Modules\Recruitment\Domain\CandidateProfileFields;
use HaHireAI\Shared\Ulid;

/**
 * Gradual, backward-compatible migration of the legacy `candidate_profiles.details`
 * JSON blob to a NORMALISED `candidate_profile_fields` table (docs/DATABASE_ARCHITECTURE.md).
 *
 * This step is ADDITIVE and non-destructive:
 *   - creates the normalised table,
 *   - BACKFILLS it from every existing `details` JSON (using the shared
 *     {@see CandidateProfileFields::flatten()} logic),
 * while the JSON column and all its readers stay untouched. CandidateProfileService
 * now DUAL-WRITES both. The JSON column is dropped only in a later, approved step
 * once every reader has moved onto the normalised table (search/screening/etc.).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->createIfNotExists('candidate_profile_fields', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');
            $t->string('field_key', 64);      // email | phone | skill(s) | education | language | certification | current_salary | availability | …
            $t->string('field_value', 500);
            $t->integer('position')->default(0);
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'user_id'], 'cpf_ws_user_idx');
            $t->index(['workspace_id', 'field_key'], 'cpf_ws_key_idx');
            $t->index('field_value', 'cpf_value_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        // Backfill from every existing details JSON (no-op on a fresh/empty DB).
        if (! $schema->hasTable('candidate_profiles')) {
            return;
        }
        $connection = $schema->connection();
        $profiles = $connection->select(
            "SELECT workspace_id, user_id, details FROM candidate_profiles WHERE details IS NOT NULL AND details <> '' AND details <> '[]' AND details <> '{}'",
        );
        $now = gmdate('Y-m-d H:i:s');
        foreach ($profiles as $p) {
            $details = is_array($p['details']) ? $p['details'] : (json_decode((string) $p['details'], true) ?: []);
            if (! is_array($details) || $details === []) {
                continue;
            }
            $ws = (string) $p['workspace_id'];
            $uid = (string) $p['user_id'];
            // Idempotent: clear any prior backfill for this profile first.
            $connection->statement('DELETE FROM candidate_profile_fields WHERE workspace_id = ? AND user_id = ?', [$ws, $uid]);
            foreach (CandidateProfileFields::flatten($details) as $row) {
                $connection->statement(
                    'INSERT INTO candidate_profile_fields (id, workspace_id, user_id, field_key, field_value, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [Ulid::generate(), $ws, $uid, $row['key'], $row['value'], $row['position'], $now],
                );
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('candidate_profile_fields');
    }
};
