<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Avatars depth (Sprint 3.3e): an AI interviewer avatar is more than CRUD — it
 * carries a system prompt, a greeting, a voice, a knowledge brief, and a status.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        foreach ([
            'prompt' => 'TEXT NULL',
            'greeting' => 'TEXT NULL',
            'voice' => "VARCHAR(64) NULL",
            'knowledge' => 'TEXT NULL',
            'status' => "VARCHAR(16) NOT NULL DEFAULT 'active'",
        ] as $col => $type) {
            if (! $schema->hasColumn('ai_avatars', $col)) {
                $schema->raw("ALTER TABLE ai_avatars ADD COLUMN {$col} {$type}");
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['status', 'knowledge', 'voice', 'greeting', 'prompt'] as $col) {
            if ($schema->hasColumn('ai_avatars', $col)) {
                $schema->raw("ALTER TABLE ai_avatars DROP COLUMN {$col}");
            }
        }
    }
};
