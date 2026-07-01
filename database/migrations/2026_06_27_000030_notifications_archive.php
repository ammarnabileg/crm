<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Notifications depth (Sprint 4c): notifications can be archived so the inbox
 * stays focused. The `type` column already doubles as the category.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (! $schema->hasColumn('notifications', 'archived_at')) {
            $schema->raw('ALTER TABLE notifications ADD COLUMN archived_at DATETIME NULL');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasColumn('notifications', 'archived_at')) {
            $schema->raw('ALTER TABLE notifications DROP COLUMN archived_at');
        }
    }
};
