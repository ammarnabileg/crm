<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * No-code Workflow Builder (docs/WORKFLOW_ENGINE.md). Extends the existing
 * Workflow Engine data model with a visual graph (nodes+edges live in the
 * existing `workflows.definition` JSON), version snapshots, a richer execution
 * trail, and per-workspace Dynamic Collections — all additive, so the engine and
 * every other feature keep working with zero workflows present.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // workflows: category/description for the library, current_version pointer.
        foreach ([
            "ALTER TABLE workflows ADD COLUMN category VARCHAR(64) NULL",
            "ALTER TABLE workflows ADD COLUMN description VARCHAR(500) NULL",
            "ALTER TABLE workflows ADD COLUMN current_version INT NOT NULL DEFAULT 1",
            "ALTER TABLE workflows ADD COLUMN summary TEXT NULL",
        ] as $sql) {
            $col = $this->colOf($sql);
            if (! $schema->hasColumn('workflows', $col)) {
                $schema->raw($sql);
            }
        }

        // Every save snapshots the full graph as an immutable version (rollback/compare).
        if (! $schema->hasTable('workflow_versions')) {
            $schema->create('workflow_versions', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('workflow_id');
                $t->integer('version')->default(1);
                $t->string('name');
                $t->json('definition');     // { nodes:[], edges:[] }
                $t->text('summary')->nullable(); // natural-language description
                $t->ulid('created_by')->nullable();
                $t->datetime('created_at')->nullable();
                $t->index(['workflow_id', 'version'], 'workflow_versions_wf_ver_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('workflow_id', 'workflows', 'id', 'CASCADE');
            });
        }

        // executions: how it was triggered, by whom, on which version, timing, IO.
        foreach ([
            "ALTER TABLE workflow_executions ADD COLUMN trigger_type VARCHAR(16) NOT NULL DEFAULT 'event'", // event|manual|schedule
            "ALTER TABLE workflow_executions ADD COLUMN triggered_by VARCHAR(26) NULL",
            "ALTER TABLE workflow_executions ADD COLUMN version INT NOT NULL DEFAULT 1",
            "ALTER TABLE workflow_executions ADD COLUMN duration_ms INT NULL",
            "ALTER TABLE workflow_executions ADD COLUMN output JSON NULL",
        ] as $sql) {
            $col = $this->colOf($sql);
            if (! $schema->hasColumn('workflow_executions', $col)) {
                $schema->raw($sql);
            }
        }

        // steps: which node ran, its type, input, timing, retries.
        foreach ([
            "ALTER TABLE workflow_steps ADD COLUMN node_id VARCHAR(64) NULL",
            "ALTER TABLE workflow_steps ADD COLUMN node_type VARCHAR(64) NULL",
            "ALTER TABLE workflow_steps ADD COLUMN input TEXT NULL",
            "ALTER TABLE workflow_steps ADD COLUMN started_at DATETIME NULL",
            "ALTER TABLE workflow_steps ADD COLUMN finished_at DATETIME NULL",
            "ALTER TABLE workflow_steps ADD COLUMN retries INT NOT NULL DEFAULT 0",
        ] as $sql) {
            $col = $this->colOf($sql);
            if (! $schema->hasColumn('workflow_steps', $col)) {
                $schema->raw($sql);
            }
        }

        // Dynamic Collections: workspaces define extra data shapes WITHOUT raw SQL
        // or new MySQL tables — the architecture stays fixed; workflows read/write
        // these via the Database nodes.
        if (! $schema->hasTable('workflow_collections')) {
            $schema->create('workflow_collections', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->string('key', 64);          // slug, unique per workspace
                $t->string('name');
                $t->json('fields');             // [ { key, label, type } ]
                $t->ulid('created_by')->nullable();
                $t->timestamps();
                $t->unique(['workspace_id', 'key'], 'workflow_collections_ws_key_uq');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            });
        }

        if (! $schema->hasTable('workflow_collection_records')) {
            $schema->create('workflow_collection_records', static function (Blueprint $t): void {
                $t->ulidPrimary();
                $t->ulid('workspace_id');
                $t->ulid('collection_id');
                $t->json('data');
                $t->ulid('created_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['collection_id'], 'workflow_collection_records_coll_idx');
                $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
                $t->foreign('collection_id', 'workflow_collections', 'id', 'CASCADE');
            });
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('workflow_collection_records');
        $schema->dropIfExists('workflow_collections');
        $schema->dropIfExists('workflow_versions');
        foreach (['category', 'description', 'current_version', 'summary'] as $c) {
            if ($schema->hasColumn('workflows', $c)) {
                $schema->raw("ALTER TABLE workflows DROP COLUMN {$c}");
            }
        }
        foreach (['trigger_type', 'triggered_by', 'version', 'duration_ms', 'output'] as $c) {
            if ($schema->hasColumn('workflow_executions', $c)) {
                $schema->raw("ALTER TABLE workflow_executions DROP COLUMN {$c}");
            }
        }
        foreach (['node_id', 'node_type', 'input', 'started_at', 'finished_at', 'retries'] as $c) {
            if ($schema->hasColumn('workflow_steps', $c)) {
                $schema->raw("ALTER TABLE workflow_steps DROP COLUMN {$c}");
            }
        }
    }

    /** Extract the column name from an "ALTER TABLE … ADD COLUMN <name> …" string. */
    private function colOf(string $sql): string
    {
        return preg_match('/ADD COLUMN (\w+)/', $sql, $m) ? $m[1] : '';
    }
};
