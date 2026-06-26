<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D2 — Workspaces & Settings (FOREIGN KEYS) — docs/database/03-Workspaces-Settings.md.
 *
 * Adds every foreign key for the D2 tables created in
 * 0020_create_workspace_settings.php. Split out so all tables (and cross-domain
 * anchors) exist before any constraint is added — create-order and cross-domain
 * cycles never break the build.
 *
 * Each ADD CONSTRAINT is guarded by hasConstraint (idempotent). FKs whose
 * referenced table is owned by another domain (D0 `lookup_values`/`currencies`/
 * `countries`, D9 `ai_providers`/`ai_models`/`tenant_ai_keys`, D10 `files`/
 * `storage_providers`) OR is a per-entity status / catalog table that lives
 * outside this file's explicit CREATE set (`integrations`, `integration_status`,
 * `domain_status`, `invitation_status`) are ALSO guarded by hasTable on the
 * referenced table, so the FK is applied only once that table exists and the
 * lead's controlled run order stays unconstrained. Built anchors `workspaces`,
 * `users`, `roles`, `memberships` are always present.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // --- workspace_settings ---
        $this->addFk($db, 'workspace_settings', 'workspace_settings_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');

        // --- workspace_branding ---
        $this->addFk($db, 'workspace_branding', 'workspace_branding_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_branding', 'workspace_branding_logo_file_id_foreign', 'logo_file_id', 'files', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'workspace_branding', 'workspace_branding_logo_dark_file_id_foreign', 'logo_dark_file_id', 'files', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'workspace_branding', 'workspace_branding_favicon_file_id_foreign', 'favicon_file_id', 'files', 'SET NULL', 'CASCADE');

        // --- workspace_billing ---
        $this->addFk($db, 'workspace_billing', 'workspace_billing_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_billing', 'workspace_billing_country_id_foreign', 'country_id', 'countries', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_billing', 'workspace_billing_currency_id_foreign', 'currency_id', 'currencies', 'RESTRICT', 'CASCADE');

        // --- workspace_ai_settings ---
        $this->addFk($db, 'workspace_ai_settings', 'workspace_ai_settings_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_ai_settings', 'workspace_ai_settings_default_provider_id_foreign', 'default_provider_id', 'ai_providers', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_ai_settings', 'workspace_ai_settings_default_model_id_foreign', 'default_model_id', 'ai_models', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_ai_settings', 'workspace_ai_settings_default_key_id_foreign', 'default_key_id', 'tenant_ai_keys', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'workspace_ai_settings', 'workspace_ai_settings_cost_currency_id_foreign', 'cost_currency_id', 'currencies', 'RESTRICT', 'CASCADE');

        // --- workspace_storage ---
        $this->addFk($db, 'workspace_storage', 'workspace_storage_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_storage', 'workspace_storage_storage_provider_id_foreign', 'storage_provider_id', 'storage_providers', 'RESTRICT', 'CASCADE');

        // --- workspace_integrations ---
        $this->addFk($db, 'workspace_integrations', 'workspace_integrations_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_integrations', 'workspace_integrations_integration_id_foreign', 'integration_id', 'integrations', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_integrations', 'workspace_integrations_integration_status_id_foreign', 'integration_status_id', 'integration_status', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_integrations', 'workspace_integrations_installed_by_foreign', 'installed_by', 'users', 'SET NULL', 'CASCADE');

        // --- workspace_domains ---
        $this->addFk($db, 'workspace_domains', 'workspace_domains_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_domains', 'workspace_domains_domain_type_id_foreign', 'domain_type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_domains', 'workspace_domains_domain_status_id_foreign', 'domain_status_id', 'domain_status', 'RESTRICT', 'CASCADE');

        // --- workspace_invitations ---
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_role_id_foreign', 'role_id', 'roles', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_invited_by_foreign', 'invited_by', 'users', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_user_id_foreign', 'user_id', 'users', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_invitation_status_id_foreign', 'invitation_status_id', 'invitation_status', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'workspace_invitations', 'workspace_invitations_membership_id_foreign', 'membership_id', 'memberships', 'SET NULL', 'CASCADE');

        // --- user_settings ---
        $this->addFk($db, 'user_settings', 'user_settings_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'user_settings', 'user_settings_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');

        // --- mail_settings ---
        $this->addFk($db, 'mail_settings', 'mail_settings_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');

        // --- global_settings --- : no foreign keys (platform-scoped key/value).
    }

    public function down(Database $db): void
    {
        $fks = [
            'workspace_settings'     => ['workspace_settings_workspace_id_foreign'],
            'workspace_branding'     => [
                'workspace_branding_workspace_id_foreign',
                'workspace_branding_logo_file_id_foreign',
                'workspace_branding_logo_dark_file_id_foreign',
                'workspace_branding_favicon_file_id_foreign',
            ],
            'workspace_billing'      => [
                'workspace_billing_workspace_id_foreign',
                'workspace_billing_country_id_foreign',
                'workspace_billing_currency_id_foreign',
            ],
            'workspace_ai_settings'  => [
                'workspace_ai_settings_workspace_id_foreign',
                'workspace_ai_settings_default_provider_id_foreign',
                'workspace_ai_settings_default_model_id_foreign',
                'workspace_ai_settings_default_key_id_foreign',
                'workspace_ai_settings_cost_currency_id_foreign',
            ],
            'workspace_storage'      => [
                'workspace_storage_workspace_id_foreign',
                'workspace_storage_storage_provider_id_foreign',
            ],
            'workspace_integrations' => [
                'workspace_integrations_workspace_id_foreign',
                'workspace_integrations_integration_id_foreign',
                'workspace_integrations_integration_status_id_foreign',
                'workspace_integrations_installed_by_foreign',
            ],
            'workspace_domains'      => [
                'workspace_domains_workspace_id_foreign',
                'workspace_domains_domain_type_id_foreign',
                'workspace_domains_domain_status_id_foreign',
            ],
            'workspace_invitations'  => [
                'workspace_invitations_workspace_id_foreign',
                'workspace_invitations_role_id_foreign',
                'workspace_invitations_invited_by_foreign',
                'workspace_invitations_user_id_foreign',
                'workspace_invitations_invitation_status_id_foreign',
                'workspace_invitations_membership_id_foreign',
            ],
            'user_settings'          => [
                'user_settings_user_id_foreign',
                'user_settings_workspace_id_foreign',
            ],
            'mail_settings'          => ['mail_settings_workspace_id_foreign'],
        ];

        foreach ($fks as $table => $constraints) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            foreach ($constraints as $constraint) {
                if ($this->hasConstraint($db, $table, $constraint)) {
                    $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
                }
            }
        }
    }

    /**
     * Idempotently add one FK. Skips if the local table is missing, the
     * constraint already exists, or the referenced table does not yet exist
     * (cross-domain / status-catalog targets), keeping the build order-agnostic.
     */
    private function addFk(
        Database $db,
        string $table,
        string $constraint,
        string $column,
        string $references,
        string $onDelete,
        string $onUpdate
    ): void {
        if (! $this->hasTable($db, $table)) {
            return;
        }
        if (! $this->hasTable($db, $references)) {
            return;
        }
        if ($this->hasConstraint($db, $table, $constraint)) {
            return;
        }
        $db->unprepared(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`)
             REFERENCES `{$references}` (`id`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

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
