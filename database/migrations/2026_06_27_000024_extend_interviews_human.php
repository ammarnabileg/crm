<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Human interviews: meeting link + structured evaluation details (spec #12). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasColumn('interviews', 'meeting_link')) {
            $schema->raw('ALTER TABLE interviews ADD COLUMN meeting_link VARCHAR(1024) NULL AFTER mode');
        }
        if (! $schema->hasColumn('interviews', 'details')) {
            $schema->raw('ALTER TABLE interviews ADD COLUMN details JSON NULL AFTER summary');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['details', 'meeting_link'] as $col) {
            if ($schema->hasColumn('interviews', $col)) {
                $schema->raw("ALTER TABLE interviews DROP COLUMN {$col}");
            }
        }
    }
};
