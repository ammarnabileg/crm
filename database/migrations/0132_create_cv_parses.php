<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * `cv_parses` — the structured result of reading a candidate's CV (docs/53 ATS).
 *
 * One row per parse: the source (`ai` when the workspace's provider read it, else
 * `heuristic`/`none`), a short summary, and the full structured payload as JSON
 * (name/contact/skills/experiences/educations/years). Keyed by `user_id` (a
 * candidate is a User) and the `workspace_id` that ran the parse; linked to the
 * stored CV `file_id` when there is one. This keeps the rich extraction intact and
 * queryable for the recruiter UI without forcing free-text dates into the strict
 * `experiences`/`educations` columns (the safe scalar fields are still mirrored onto
 * `candidate_profiles` by CvService).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            'CREATE TABLE IF NOT EXISTS `cv_parses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `file_id` BIGINT UNSIGNED NULL,
                `source` VARCHAR(20) NOT NULL DEFAULT "none",
                `confident` TINYINT(1) NOT NULL DEFAULT 0,
                `summary` TEXT NULL,
                `data` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `cv_parses_uuid_unique` (`uuid`),
                KEY `cv_parses_user_index` (`user_id`, `created_at`),
                KEY `cv_parses_workspace_index` (`workspace_id`, `created_at`),
                CONSTRAINT `cv_parses_data_json` CHECK (`data` IS NULL OR JSON_VALID(`data`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `cv_parses`');
    }
};
