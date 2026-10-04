<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Enrich jobs with seniority + salary range + currency (recruitment spec #1). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasColumn('jobs', 'seniority')) {
            $schema->raw("ALTER TABLE jobs ADD COLUMN seniority VARCHAR(32) NULL AFTER title");
        }
        if (! $schema->hasColumn('jobs', 'salary_min')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN salary_min INT NULL AFTER seniority');
        }
        if (! $schema->hasColumn('jobs', 'salary_max')) {
            $schema->raw('ALTER TABLE jobs ADD COLUMN salary_max INT NULL AFTER salary_min');
        }
        if (! $schema->hasColumn('jobs', 'currency')) {
            $schema->raw("ALTER TABLE jobs ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'USD' AFTER salary_max");
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['currency', 'salary_max', 'salary_min', 'seniority'] as $col) {
            if ($schema->hasColumn('jobs', $col)) {
                $schema->raw("ALTER TABLE jobs DROP COLUMN {$col}");
            }
        }
    }
};
