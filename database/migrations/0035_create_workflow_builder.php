<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P3: Workflow Builder (docs/51 §8; D11 extension).
 *
 * A visual, no-code, no-prompt builder: a company designs interview logic as a
 * DIRECTED GRAPH of typed nodes (Start, Ask/Generate Question, Wait/Evaluate
 * Answer, AI Analysis, If/Else condition, Generate Follow-up, Score Candidate,
 * Generate Report, Human Approval, Finish). Stored as `interview_workflows` +
 * `workflow_nodes` + `workflow_edges`; published as immutable `workflow_versions`
 * snapshots; executed by `App\Services\Workflow\WorkflowRuntime` against
 * `workflow_runs` + `workflow_run_steps` (the per-interview execution audit).
 *
 * Run steps reference nodes by `node_key` (not an FK) because a run executes a
 * FROZEN version snapshot — the live node may later change or be deleted, but the
 * run audit must stay stable and reproducible (same principle as P2's evaluation
 * version snapshots).
 *
 * Config-driven (no ENUMs): node types and run/step statuses are seeded
 * `lookup_values` catalogs. Tenant-scoped via `workspace_id`, uuid + timestamps,
 * idempotent via information_schema guards. Self-contained: all FK targets
 * (workspaces, users, interviews, lookup_values) already exist.
 */
return new class extends Migration {
    /** Node-type catalog (key, label, sort). */
    private array $nodeTypes = [
        ['start', 'Start', 1],
        ['ask_question', 'Ask Question', 2],
        ['generate_question', 'Generate Question', 3],
        ['wait_answer', 'Wait Answer', 4],
        ['evaluate_answer', 'Evaluate Answer', 5],
        ['ai_analysis', 'AI Analysis', 6],
        ['condition', 'If / Else', 7],
        ['generate_follow_up', 'Generate Follow-up', 8],
        ['score_candidate', 'Score Candidate', 9],
        ['generate_report', 'Generate Report', 10],
        ['human_approval', 'Human Approval', 11],
        ['finish', 'Finish', 12],
    ];

    /** Run-status catalog. */
    private array $runStatuses = [
        ['pending', 'Pending', 1],
        ['running', 'Running', 2],
        ['awaiting', 'Awaiting Input', 3],
        ['completed', 'Completed', 4],
        ['failed', 'Failed', 5],
        ['canceled', 'Canceled', 6],
    ];

    /** Step-status catalog. */
    private array $stepStatuses = [
        ['pending', 'Pending', 1],
        ['running', 'Running', 2],
        ['awaiting', 'Awaiting Input', 3],
        ['completed', 'Completed', 4],
        ['skipped', 'Skipped', 5],
        ['failed', 'Failed', 6],
    ];

    public function up(Database $db): void
    {
        $this->seedCategory($db, 'workflow_node_type', 'Workflow Node Type', $this->nodeTypes);
        $this->seedCategory($db, 'workflow_run_status', 'Workflow Run Status', $this->runStatuses);
        $this->seedCategory($db, 'workflow_step_status', 'Workflow Step Status', $this->stepStatuses);

        if (! $this->hasTable($db, 'interview_workflows')) {
            $db->unprepared(
                "CREATE TABLE `interview_workflows` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `slug` VARCHAR(160) NOT NULL,
                    `description` TEXT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `version` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_workflows_uuid_unique` (`uuid`),
                    UNIQUE KEY `interview_workflows_workspace_slug_unique` (`workspace_id`, `slug`),
                    KEY `interview_workflows_workspace_id_index` (`workspace_id`),
                    KEY `interview_workflows_created_by_index` (`created_by`),
                    KEY `interview_workflows_deleted_at_index` (`deleted_at`),
                    CONSTRAINT `interview_workflows_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_workflows_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workflow_nodes')) {
            $db->unprepared(
                "CREATE TABLE `workflow_nodes` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `workflow_id` BIGINT UNSIGNED NOT NULL,
                    `node_key` VARCHAR(60) NOT NULL,
                    `type_id` BIGINT UNSIGNED NOT NULL,
                    `label` VARCHAR(150) NOT NULL,
                    `config` JSON NULL,
                    `position_x` INT NOT NULL DEFAULT 0,
                    `position_y` INT NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workflow_nodes_uuid_unique` (`uuid`),
                    UNIQUE KEY `workflow_nodes_workflow_key_unique` (`workflow_id`, `node_key`),
                    KEY `workflow_nodes_workspace_id_index` (`workspace_id`),
                    KEY `workflow_nodes_workflow_id_index` (`workflow_id`),
                    KEY `workflow_nodes_type_id_index` (`type_id`),
                    CONSTRAINT `workflow_nodes_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_nodes_workflow_id_foreign` FOREIGN KEY (`workflow_id`)
                        REFERENCES `interview_workflows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_nodes_type_id_foreign` FOREIGN KEY (`type_id`)
                        REFERENCES `lookup_values` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workflow_edges')) {
            $db->unprepared(
                "CREATE TABLE `workflow_edges` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `workflow_id` BIGINT UNSIGNED NOT NULL,
                    `from_node_id` BIGINT UNSIGNED NOT NULL,
                    `to_node_id` BIGINT UNSIGNED NOT NULL,
                    `label` VARCHAR(120) NULL,
                    `condition` JSON NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workflow_edges_uuid_unique` (`uuid`),
                    KEY `workflow_edges_workspace_id_index` (`workspace_id`),
                    KEY `workflow_edges_workflow_id_index` (`workflow_id`),
                    KEY `workflow_edges_from_node_id_index` (`from_node_id`),
                    KEY `workflow_edges_to_node_id_index` (`to_node_id`),
                    CONSTRAINT `workflow_edges_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_edges_workflow_id_foreign` FOREIGN KEY (`workflow_id`)
                        REFERENCES `interview_workflows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_edges_from_node_id_foreign` FOREIGN KEY (`from_node_id`)
                        REFERENCES `workflow_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_edges_to_node_id_foreign` FOREIGN KEY (`to_node_id`)
                        REFERENCES `workflow_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workflow_versions')) {
            $db->unprepared(
                "CREATE TABLE `workflow_versions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `workflow_id` BIGINT UNSIGNED NOT NULL,
                    `version` INT UNSIGNED NOT NULL,
                    `snapshot` JSON NOT NULL,
                    `notes` VARCHAR(255) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                    `published_by` BIGINT UNSIGNED NULL,
                    `published_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workflow_versions_uuid_unique` (`uuid`),
                    UNIQUE KEY `workflow_versions_workflow_version_unique` (`workflow_id`, `version`),
                    KEY `workflow_versions_workspace_id_index` (`workspace_id`),
                    KEY `workflow_versions_workflow_id_index` (`workflow_id`),
                    KEY `workflow_versions_active_index` (`workflow_id`, `is_active`),
                    KEY `workflow_versions_published_by_index` (`published_by`),
                    CONSTRAINT `workflow_versions_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_versions_workflow_id_foreign` FOREIGN KEY (`workflow_id`)
                        REFERENCES `interview_workflows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_versions_published_by_foreign` FOREIGN KEY (`published_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workflow_runs')) {
            $db->unprepared(
                "CREATE TABLE `workflow_runs` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `workflow_id` BIGINT UNSIGNED NOT NULL,
                    `version_id` BIGINT UNSIGNED NULL,
                    `interview_id` BIGINT UNSIGNED NULL,
                    `status_id` BIGINT UNSIGNED NOT NULL,
                    `current_node_key` VARCHAR(60) NULL,
                    `context` JSON NULL,
                    `started_at` TIMESTAMP NULL DEFAULT NULL,
                    `ended_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workflow_runs_uuid_unique` (`uuid`),
                    KEY `workflow_runs_workspace_id_index` (`workspace_id`),
                    KEY `workflow_runs_workflow_id_index` (`workflow_id`),
                    KEY `workflow_runs_version_id_index` (`version_id`),
                    KEY `workflow_runs_interview_id_index` (`interview_id`),
                    KEY `workflow_runs_status_id_index` (`status_id`),
                    CONSTRAINT `workflow_runs_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_runs_workflow_id_foreign` FOREIGN KEY (`workflow_id`)
                        REFERENCES `interview_workflows` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_runs_version_id_foreign` FOREIGN KEY (`version_id`)
                        REFERENCES `workflow_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `workflow_runs_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `workflow_runs_status_id_foreign` FOREIGN KEY (`status_id`)
                        REFERENCES `lookup_values` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workflow_run_steps')) {
            $db->unprepared(
                "CREATE TABLE `workflow_run_steps` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `run_id` BIGINT UNSIGNED NOT NULL,
                    `node_key` VARCHAR(60) NOT NULL,
                    `node_type` VARCHAR(60) NULL,
                    `status_id` BIGINT UNSIGNED NOT NULL,
                    `sequence` INT NOT NULL DEFAULT 0,
                    `input` JSON NULL,
                    `output` JSON NULL,
                    `decision` VARCHAR(120) NULL,
                    `entered_at` TIMESTAMP NULL DEFAULT NULL,
                    `exited_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workflow_run_steps_uuid_unique` (`uuid`),
                    KEY `workflow_run_steps_workspace_id_index` (`workspace_id`),
                    KEY `workflow_run_steps_run_id_index` (`run_id`),
                    KEY `workflow_run_steps_status_id_index` (`status_id`),
                    KEY `workflow_run_steps_run_seq_index` (`run_id`, `sequence`),
                    CONSTRAINT `workflow_run_steps_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_run_steps_run_id_foreign` FOREIGN KEY (`run_id`)
                        REFERENCES `workflow_runs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workflow_run_steps_status_id_foreign` FOREIGN KEY (`status_id`)
                        REFERENCES `lookup_values` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (['workflow_run_steps', 'workflow_runs', 'workflow_versions', 'workflow_edges', 'workflow_nodes', 'interview_workflows'] as $t) {
            $db->unprepared("DROP TABLE IF EXISTS `{$t}`");
        }
        // Leave the seeded lookup categories in place (harmless if re-run).
    }

    /**
     * Idempotently seed a system lookup category and its values.
     *
     * @param array<int, array{0:string,1:string,2:int}> $values
     */
    private function seedCategory(Database $db, string $key, string $label, array $values): void
    {
        $now = date('Y-m-d H:i:s');

        $categoryId = (int) $db->scalar(
            'SELECT id FROM lookup_categories WHERE `key` = ? AND workspace_id IS NULL LIMIT 1',
            [$key]
        );
        if ($categoryId === 0) {
            $db->table('lookup_categories')->insert([
                'uuid'        => (string) $db->scalar('SELECT UUID()'),
                'workspace_id' => null,
                'key'         => $key,
                'label'       => $label,
                'is_system'   => 1,
                'is_active'   => 1,
                'sort_order'  => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $categoryId = (int) $db->scalar(
                'SELECT id FROM lookup_categories WHERE `key` = ? AND workspace_id IS NULL LIMIT 1',
                [$key]
            );
        }

        foreach ($values as [$vKey, $vLabel, $sort]) {
            $exists = (int) $db->scalar(
                'SELECT COUNT(*) FROM lookup_values WHERE category_id = ? AND `key` = ? AND workspace_id IS NULL',
                [$categoryId, $vKey]
            );
            if ($exists === 0) {
                $db->table('lookup_values')->insert([
                    'uuid'        => (string) $db->scalar('SELECT UUID()'),
                    'category_id' => $categoryId,
                    'workspace_id' => null,
                    'key'         => $vKey,
                    'label'       => $vLabel,
                    'sort_order'  => $sort,
                    'is_default'  => 0,
                    'is_system'   => 1,
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
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
