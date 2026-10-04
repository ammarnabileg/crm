<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Observability (docs/OBSERVABILITY.md): captured errors, alerts, backups. */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Captured exceptions/errors (fed by the global ErrorHandler via the bus).
        $schema->create('error_events', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('level', 16)->default('error');
            $t->text('message');
            $t->string('exception_class', 191)->nullable();
            $t->string('file', 512)->nullable();
            $t->integer('line')->nullable();
            $t->string('fingerprint', 64)->nullable();  // group identical errors
            $t->json('context')->nullable();
            $t->ulid('workspace_id')->nullable();        // best-effort; no FK (errors precede scope)
            $t->datetime('occurred_at');
            $t->datetime('created_at')->nullable();
            $t->index(['occurred_at'], 'error_events_occurred_idx');
            $t->index(['fingerprint'], 'error_events_fingerprint_idx');
        });

        // Monitor alerts (opened/resolved by the MonitorService tick).
        $schema->create('alerts', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('monitor_key', 64);
            $t->string('severity', 16)->default('warning'); // info|warning|critical
            $t->string('status', 16)->default('open');       // open|resolved
            $t->string('title');
            $t->text('detail')->nullable();
            $t->integer('value')->nullable();
            $t->datetime('opened_at');
            $t->datetime('resolved_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['monitor_key', 'status'], 'alerts_key_status_idx');
        });

        // Backup run records (the operational source of truth for restores).
        $schema->create('backups', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('kind', 32)->default('logical');
            $t->string('status', 16)->default('running'); // running|completed|failed
            $t->string('path', 1024)->nullable();
            $t->bigInteger('size_bytes')->nullable();
            $t->integer('tables_count')->nullable();
            $t->text('error')->nullable();
            $t->datetime('started_at');
            $t->datetime('finished_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['created_at'], 'backups_created_idx');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['error_events', 'alerts', 'backups'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
