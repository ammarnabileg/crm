<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Candidate portal: counter-offers (candidate proposes back, with a note) and
 * the candidate's personal data captured at registration / editable on Profile.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Offers gain a free-text note and who proposed them (company vs candidate).
        if (! $schema->hasColumn('offers', 'note')) {
            $schema->raw('ALTER TABLE offers ADD COLUMN note TEXT NULL AFTER status');
        }
        if (! $schema->hasColumn('offers', 'proposed_by')) {
            $schema->raw("ALTER TABLE offers ADD COLUMN proposed_by VARCHAR(16) NOT NULL DEFAULT 'company' AFTER note");
        }

        // Candidate personal data lives on the (global) user identity.
        if (! $schema->hasColumn('users', 'phone')) {
            $schema->raw('ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL AFTER email');
        }
        if (! $schema->hasColumn('users', 'years_experience')) {
            $schema->raw('ALTER TABLE users ADD COLUMN years_experience SMALLINT UNSIGNED NULL AFTER phone');
        }
        if (! $schema->hasColumn('users', 'target_salary')) {
            $schema->raw('ALTER TABLE users ADD COLUMN target_salary BIGINT UNSIGNED NULL AFTER years_experience');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['target_salary', 'years_experience', 'phone'] as $col) {
            if ($schema->hasColumn('users', $col)) {
                $schema->raw("ALTER TABLE users DROP COLUMN {$col}");
            }
        }
        foreach (['proposed_by', 'note'] as $col) {
            if ($schema->hasColumn('offers', $col)) {
                $schema->raw("ALTER TABLE offers DROP COLUMN {$col}");
            }
        }
    }
};
