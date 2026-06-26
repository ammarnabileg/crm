<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D0 cross-cutting polymorphic tables (docs/database/01) — the DRY foundation that
 * replaces dozens of per-entity copies: `translations`, `attachments`, `notes`,
 * `tags`, `taggables`, `status_histories`. Each associates to any owner entity
 * through a `(<x>_type, <x>_id)` morph pair (a stable singular entity key + the
 * owner's numeric id) with a MANDATORY composite index and NO database FK on the
 * morph pair (app-enforced — the documented exception to "every relationship is an
 * FK"). Genuine relationships (workspace_id, file_id, tag_id, users, lookup_values)
 * are real FKs, added in the companion FK migration (0129).
 *
 * CREATE-only (columns + indexes); foreign keys live in 0129 so every referenced
 * table (incl. D10 `files`) exists first. Idempotent via information_schema guards.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // translations — polymorphic per-field translation store (no soft delete).
        if (! $this->hasTable($db, 'translations')) {
            $db->unprepared(
                "CREATE TABLE `translations` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NULL,
                    `translatable_type` VARCHAR(120) NOT NULL,
                    `translatable_id` BIGINT UNSIGNED NOT NULL,
                    `locale` VARCHAR(10) NOT NULL,
                    `field` VARCHAR(60) NOT NULL,
                    `value` TEXT NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `translations_uuid_unique` (`uuid`),
                    UNIQUE KEY `translations_morph_locale_field_unique` (`translatable_type`, `translatable_id`, `locale`, `field`),
                    KEY `translations_translatable_index` (`translatable_type`, `translatable_id`),
                    KEY `translations_workspace_id_index` (`workspace_id`),
                    KEY `translations_locale_index` (`locale`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // attachments — polymorphic link between a stored file and any entity (soft delete).
        if (! $this->hasTable($db, 'attachments')) {
            $db->unprepared(
                "CREATE TABLE `attachments` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `file_id` BIGINT UNSIGNED NOT NULL,
                    `attachable_type` VARCHAR(120) NOT NULL,
                    `attachable_id` BIGINT UNSIGNED NOT NULL,
                    `collection` VARCHAR(60) NULL,
                    `title` VARCHAR(160) NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `uploaded_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `attachments_uuid_unique` (`uuid`),
                    KEY `attachments_attachable_index` (`attachable_type`, `attachable_id`),
                    KEY `attachments_workspace_morph_index` (`workspace_id`, `attachable_type`, `attachable_id`),
                    KEY `attachments_workspace_id_index` (`workspace_id`),
                    KEY `attachments_file_id_index` (`file_id`),
                    KEY `attachments_uploaded_by_index` (`uploaded_by`),
                    KEY `attachments_collection_index` (`collection`),
                    KEY `attachments_deleted_at_index` (`deleted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // notes — polymorphic free-text notes/comments (soft delete; fulltext body).
        if (! $this->hasTable($db, 'notes')) {
            $db->unprepared(
                "CREATE TABLE `notes` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `notable_type` VARCHAR(120) NOT NULL,
                    `notable_id` BIGINT UNSIGNED NOT NULL,
                    `type_id` BIGINT UNSIGNED NULL,
                    `user_id` BIGINT UNSIGNED NULL,
                    `body` TEXT NOT NULL,
                    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_private` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `notes_uuid_unique` (`uuid`),
                    KEY `notes_notable_index` (`notable_type`, `notable_id`),
                    KEY `notes_workspace_morph_index` (`workspace_id`, `notable_type`, `notable_id`),
                    KEY `notes_workspace_id_index` (`workspace_id`),
                    KEY `notes_type_id_index` (`type_id`),
                    KEY `notes_user_id_index` (`user_id`),
                    KEY `notes_deleted_at_index` (`deleted_at`),
                    FULLTEXT KEY `notes_body_fulltext` (`body`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // tags — tenant-defined labels applied via taggables (soft delete).
        if (! $this->hasTable($db, 'tags')) {
            $db->unprepared(
                "CREATE TABLE `tags` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(80) NOT NULL,
                    `slug` VARCHAR(90) NOT NULL,
                    `color` VARCHAR(20) NULL,
                    `description` VARCHAR(255) NULL,
                    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tags_uuid_unique` (`uuid`),
                    UNIQUE KEY `tags_workspace_slug_unique` (`workspace_id`, `slug`),
                    KEY `tags_workspace_id_index` (`workspace_id`),
                    KEY `tags_created_by_index` (`created_by`),
                    KEY `tags_name_index` (`name`),
                    KEY `tags_deleted_at_index` (`deleted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // taggables — polymorphic pivot tag<->entity (pure pivot: no uuid, no soft delete).
        if (! $this->hasTable($db, 'taggables')) {
            $db->unprepared(
                "CREATE TABLE `taggables` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `tag_id` BIGINT UNSIGNED NOT NULL,
                    `taggable_type` VARCHAR(120) NOT NULL,
                    `taggable_id` BIGINT UNSIGNED NOT NULL,
                    `tagged_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `taggables_tag_morph_unique` (`tag_id`, `taggable_type`, `taggable_id`),
                    KEY `taggables_taggable_index` (`taggable_type`, `taggable_id`),
                    KEY `taggables_workspace_morph_index` (`workspace_id`, `taggable_type`, `taggable_id`),
                    KEY `taggables_tag_id_index` (`tag_id`),
                    KEY `taggables_workspace_id_index` (`workspace_id`),
                    KEY `taggables_tagged_by_index` (`tagged_by`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // status_histories — polymorphic, append-only status-transition ledger (no soft delete).
        // scale: partition candidate by RANGE(created_at) (applied later as an ops step).
        if (! $this->hasTable($db, 'status_histories')) {
            $db->unprepared(
                "CREATE TABLE `status_histories` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `subject_type` VARCHAR(120) NOT NULL,
                    `subject_id` BIGINT UNSIGNED NOT NULL,
                    `status_type` VARCHAR(120) NULL,
                    `from_status_id` BIGINT UNSIGNED NULL,
                    `to_status_id` BIGINT UNSIGNED NOT NULL,
                    `from_status_key` VARCHAR(60) NULL,
                    `to_status_key` VARCHAR(60) NULL,
                    `changed_by` BIGINT UNSIGNED NULL,
                    `note` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `status_histories_uuid_unique` (`uuid`),
                    KEY `status_histories_subject_index` (`subject_type`, `subject_id`),
                    KEY `status_histories_workspace_morph_index` (`workspace_id`, `subject_type`, `subject_id`),
                    KEY `status_histories_workspace_id_index` (`workspace_id`),
                    KEY `status_histories_changed_by_index` (`changed_by`),
                    KEY `status_histories_created_at_index` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (['status_histories', 'taggables', 'tags', 'notes', 'attachments', 'translations'] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
