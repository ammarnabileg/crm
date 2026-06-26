<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D8 — AI & Notifications: FOREIGN KEYS (paired with 0026_create_ai_notifications).
 *
 * Every table in this domain is created (with all indexes) in 0026 with NO FK
 * constraints; this file adds them idempotently so create-order / cross-domain
 * cycles never break the build (Bible two-file pattern).
 *
 * FK-light by design (Bible §4/§7) — these extreme/high-volume append tables get
 * NO foreign keys here; their workspace_id/created_at/etc. are plain indexed
 * BIGINTs validated at the app layer:
 *   ai_requests, ai_responses, ai_logs, ai_errors, notification_logs.
 *
 * tenant_ai_keys mapping: the blueprint's `tenant_ai_keys` is BUILT as
 * `ai_credentials`; the only reference to it (ai_requests.tenant_ai_key_id) is on
 * an FK-light table, so no hard FK targets it. (When ai_credentials is later
 * renamed to tenant_ai_keys, nothing here changes.)
 *
 * notifications / notification_queue: the doc models these as time-partitioned
 * (FKs then logical/app-enforced), but since 0026 leaves them partition-READY
 * only (no physical PARTITION), the documented FKs apply directly and are added
 * here.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // ai_models
        $this->addFk($db, 'ai_models', 'ai_models_provider_id_foreign', 'provider_id', 'ai_providers', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'ai_models', 'ai_models_currency_id_foreign', 'currency_id', 'currencies', 'RESTRICT', 'CASCADE');

        // ai_usage
        $this->addFk($db, 'ai_usage', 'ai_usage_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'ai_usage', 'ai_usage_provider_id_foreign', 'provider_id', 'ai_providers', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'ai_usage', 'ai_usage_model_id_foreign', 'model_id', 'ai_models', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'ai_usage', 'ai_usage_currency_id_foreign', 'currency_id', 'currencies', 'RESTRICT', 'CASCADE');

        // ai_costs
        $this->addFk($db, 'ai_costs', 'ai_costs_model_id_foreign', 'model_id', 'ai_models', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'ai_costs', 'ai_costs_currency_id_foreign', 'currency_id', 'currencies', 'RESTRICT', 'CASCADE');

        // ai_cache
        $this->addFk($db, 'ai_cache', 'ai_cache_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'ai_cache', 'ai_cache_model_id_foreign', 'model_id', 'ai_models', 'SET NULL', 'CASCADE');

        // notifications
        $this->addFk($db, 'notifications', 'notifications_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notifications', 'notifications_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notifications', 'notifications_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'notifications', 'notifications_channel_id_foreign', 'channel_id', 'notification_channels', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'notifications', 'notifications_actor_id_foreign', 'actor_id', 'users', 'SET NULL', 'CASCADE');

        // notification_templates
        $this->addFk($db, 'notification_templates', 'notification_templates_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_templates', 'notification_templates_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'notification_templates', 'notification_templates_channel_id_foreign', 'channel_id', 'notification_channels', 'RESTRICT', 'CASCADE');

        // notification_preferences
        $this->addFk($db, 'notification_preferences', 'notification_preferences_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_preferences', 'notification_preferences_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_preferences', 'notification_preferences_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'notification_preferences', 'notification_preferences_channel_id_foreign', 'channel_id', 'notification_channels', 'RESTRICT', 'CASCADE');

        // notification_queue
        $this->addFk($db, 'notification_queue', 'notification_queue_notification_id_foreign', 'notification_id', 'notifications', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_queue', 'notification_queue_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_queue', 'notification_queue_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'notification_queue', 'notification_queue_channel_id_foreign', 'channel_id', 'notification_channels', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'notification_queue', 'notification_queue_template_id_foreign', 'template_id', 'notification_templates', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'notification_queue', 'notification_queue_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
    }

    public function down(Database $db): void
    {
        $fks = [
            ['ai_models', 'ai_models_provider_id_foreign'],
            ['ai_models', 'ai_models_currency_id_foreign'],
            ['ai_usage', 'ai_usage_workspace_id_foreign'],
            ['ai_usage', 'ai_usage_provider_id_foreign'],
            ['ai_usage', 'ai_usage_model_id_foreign'],
            ['ai_usage', 'ai_usage_currency_id_foreign'],
            ['ai_costs', 'ai_costs_model_id_foreign'],
            ['ai_costs', 'ai_costs_currency_id_foreign'],
            ['ai_cache', 'ai_cache_workspace_id_foreign'],
            ['ai_cache', 'ai_cache_model_id_foreign'],
            ['notifications', 'notifications_workspace_id_foreign'],
            ['notifications', 'notifications_user_id_foreign'],
            ['notifications', 'notifications_type_id_foreign'],
            ['notifications', 'notifications_channel_id_foreign'],
            ['notifications', 'notifications_actor_id_foreign'],
            ['notification_templates', 'notification_templates_workspace_id_foreign'],
            ['notification_templates', 'notification_templates_type_id_foreign'],
            ['notification_templates', 'notification_templates_channel_id_foreign'],
            ['notification_preferences', 'notification_preferences_user_id_foreign'],
            ['notification_preferences', 'notification_preferences_workspace_id_foreign'],
            ['notification_preferences', 'notification_preferences_type_id_foreign'],
            ['notification_preferences', 'notification_preferences_channel_id_foreign'],
            ['notification_queue', 'notification_queue_notification_id_foreign'],
            ['notification_queue', 'notification_queue_workspace_id_foreign'],
            ['notification_queue', 'notification_queue_user_id_foreign'],
            ['notification_queue', 'notification_queue_channel_id_foreign'],
            ['notification_queue', 'notification_queue_template_id_foreign'],
            ['notification_queue', 'notification_queue_type_id_foreign'],
        ];
        foreach ($fks as [$table, $fk]) {
            if ($this->hasTable($db, $table) && $this->hasConstraint($db, $table, $fk)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
            }
        }
    }

    /**
     * Idempotently add a single-column FK; skips if the table/constraint is
     * absent (so the file is safe to re-run and tolerant of partial builds).
     */
    private function addFk(
        Database $db,
        string $table,
        string $constraint,
        string $column,
        string $refTable,
        string $onDelete,
        string $onUpdate
    ): void {
        if (! $this->hasTable($db, $table) || ! $this->hasTable($db, $refTable)) {
            return;
        }
        if ($this->hasConstraint($db, $table, $constraint)) {
            return;
        }
        $db->unprepared(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) "
            . "REFERENCES `{$refTable}` (`id`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

    // -----------------------------------------------------------------
    // Idempotency helpers
    // -----------------------------------------------------------------

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }

    private function hasIndex(Database $db, string $table, string $index): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }
};
