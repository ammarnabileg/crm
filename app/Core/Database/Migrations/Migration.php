<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database\Migrations;

use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Base migration. Each migration file returns an anonymous class extending this.
 * Forward-only by policy; down() is best-effort for local rollback.
 * See docs/UPDATE_POLICY.md, docs/DATABASE_ARCHITECTURE.md.
 */
abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): void;

    public function down(SchemaBuilder $schema): void
    {
    }
}
