<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Blueprint cutover R1 (approved): the tenant entity becomes a typed WORKSPACE.
 *
 *  - `companies`  -> `workspaces`
 *  - `company_id` -> `workspace_id` on every tenant-scoped table (FK dropped,
 *    column renamed, FK re-added pointing at `workspaces`)
 *  - new global `workspace_types` catalog (company/organization/university/
 *    government/hospital/school/agency/nonprofit) + `workspaces.workspace_type_id`
 *
 * Same architecture, any organisation type. Idempotent via information_schema
 * guards so it is safe on fresh installs (after 0001–0016) and existing data.
 */
return new class extends Migration {
    /** Tenant tables carrying company_id, with their existing FK name + nullability. */
    private array $children = [
        'memberships'         => ['fk' => 'memberships_company_id_foreign', 'null' => false],
        'roles'               => ['fk' => 'roles_company_id_foreign', 'null' => true],
        'subscriptions'       => ['fk' => 'subscriptions_company_id_foreign', 'null' => false],
        'ai_credentials'      => ['fk' => 'ai_credentials_company_id_foreign', 'null' => false],
        'settings'            => ['fk' => 'settings_company_id_foreign', 'null' => false],
        'onboarding_progress' => ['fk' => 'onboarding_company_id_foreign', 'null' => true],
        'activity_log'        => ['fk' => 'activity_log_company_id_foreign', 'null' => true],
    ];

    public function up(Database $db): void
    {
        // 1) workspace_types catalog.
        if (! $this->hasTable($db, 'workspace_types')) {
            $db->unprepared(
                "CREATE TABLE `workspace_types` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `key` VARCHAR(40) NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `description` VARCHAR(255) NULL,
                    `icon` VARCHAR(60) NULL,
                    `is_system` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workspace_types_uuid_unique` (`uuid`),
                    UNIQUE KEY `workspace_types_key_unique` (`key`),
                    KEY `workspace_types_active_index` (`is_active`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $types = [
                ['company', 'Company', 'A private or public company / business.'],
                ['organization', 'Organization', 'A general organization or association.'],
                ['university', 'University', 'A university or higher-education institution.'],
                ['government', 'Government', 'A government body or public sector entity.'],
                ['hospital', 'Hospital', 'A hospital or healthcare provider.'],
                ['school', 'School', 'A school or K-12 institution.'],
                ['agency', 'Agency', 'A recruitment or staffing agency.'],
                ['nonprofit', 'Nonprofit', 'A non-profit or NGO.'],
            ];
            $order = 0;
            foreach ($types as [$key, $label, $desc]) {
                $db->table('workspace_types')->insert([
                    'uuid'       => $this->uuid($db),
                    'key'        => $key,
                    'label'      => $label,
                    'description' => $desc,
                    'is_system'  => 1,
                    'is_active'  => 1,
                    'sort_order' => $order++,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 2) Rename the tenant root: companies -> workspaces (child FKs follow).
        if ($this->hasTable($db, 'companies') && ! $this->hasTable($db, 'workspaces')) {
            $db->unprepared('RENAME TABLE `companies` TO `workspaces`');
        }

        // 3) Add workspaces.workspace_type_id (default to the "company" type).
        if ($this->hasTable($db, 'workspaces') && ! $this->hasColumn($db, 'workspaces', 'workspace_type_id')) {
            $companyTypeId = (int) $db->table('workspace_types')->where('key', '=', 'company')->value('id');
            $db->unprepared('ALTER TABLE `workspaces` ADD COLUMN `workspace_type_id` BIGINT UNSIGNED NULL AFTER `uuid`');
            $db->table('workspaces')->update(['workspace_type_id' => $companyTypeId]);
            $db->unprepared('ALTER TABLE `workspaces` MODIFY `workspace_type_id` BIGINT UNSIGNED NOT NULL');
            $db->unprepared('ALTER TABLE `workspaces` ADD KEY `workspaces_workspace_type_id_index` (`workspace_type_id`)');
            $db->unprepared(
                'ALTER TABLE `workspaces` ADD CONSTRAINT `workspaces_workspace_type_id_foreign`
                 FOREIGN KEY (`workspace_type_id`) REFERENCES `workspace_types` (`id`)
                 ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }

        // 4) Rename company_id -> workspace_id on every child (drop FK, rename, re-add).
        foreach ($this->children as $table => $meta) {
            if (! $this->hasTable($db, $table) || ! $this->hasColumn($db, $table, 'company_id')) {
                continue;
            }

            if ($this->hasConstraint($db, $table, $meta['fk'])) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$meta['fk']}`");
            }

            $nullSql = $meta['null'] ? 'NULL' : 'NOT NULL';
            $db->unprepared("ALTER TABLE `{$table}` CHANGE `company_id` `workspace_id` BIGINT UNSIGNED {$nullSql}");

            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_workspace_id_foreign`
                 FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
                 ON DELETE CASCADE ON UPDATE CASCADE"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->children as $table => $meta) {
            if (! $this->hasTable($db, $table) || ! $this->hasColumn($db, $table, 'workspace_id')) {
                continue;
            }
            if ($this->hasConstraint($db, $table, "{$table}_workspace_id_foreign")) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_workspace_id_foreign`");
            }
            $nullSql = $meta['null'] ? 'NULL' : 'NOT NULL';
            $db->unprepared("ALTER TABLE `{$table}` CHANGE `workspace_id` `company_id` BIGINT UNSIGNED {$nullSql}");
            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$meta['fk']}`
                 FOREIGN KEY (`company_id`) REFERENCES `workspaces` (`id`)
                 ON DELETE CASCADE ON UPDATE CASCADE"
            );
        }

        if ($this->hasColumn($db, 'workspaces', 'workspace_type_id')) {
            if ($this->hasConstraint($db, 'workspaces', 'workspaces_workspace_type_id_foreign')) {
                $db->unprepared('ALTER TABLE `workspaces` DROP FOREIGN KEY `workspaces_workspace_type_id_foreign`');
            }
            $db->unprepared('ALTER TABLE `workspaces` DROP COLUMN `workspace_type_id`');
        }

        if ($this->hasTable($db, 'workspaces') && ! $this->hasTable($db, 'companies')) {
            $db->unprepared('RENAME TABLE `workspaces` TO `companies`');
        }

        $db->unprepared('DROP TABLE IF EXISTS `workspace_types`');

        // Restore original company_id FK constraint names referencing companies.
        foreach ($this->children as $table => $meta) {
            if ($this->hasConstraint($db, $table, "{$table}_workspace_id_foreign")) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_workspace_id_foreign`");
                $db->unprepared(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$meta['fk']}`
                     FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
                     ON DELETE CASCADE ON UPDATE CASCADE"
                );
            }
        }
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
};
