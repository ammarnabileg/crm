<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P6: Multi-Agent layer + Explainable AI + Decision
 * aggregation (docs/51 §2, §3, §15).
 *
 * §2 (Multi-Agent): nine single-responsibility "expert" agents each judge one lens
 * of an interview — HR, Technical, Behaviour, Psychometric, Communication,
 * Language, Culture-fit, Risk, plus a Decision agent that AGGREGATES the eight
 * scoring lenses into one verdict. The agent CATALOG lives in `ai_agents`: system
 * rows (workspace_id IS NULL, is_system=1) are the platform defaults; a tenant may
 * add or override an agent with its own workspace_id (same merge model as
 * `interview_states` — system + tenant resolved at runtime). Each agent carries a
 * `weight` so the eight scoring agents form a 0–100 weighted rubric (the Decision
 * agent has weight 0 — it aggregates, it does not score).
 *
 * §15 (Explainable AI): every agent execution is recorded in `agent_runs` — its
 * score / max_score / confidence / structured `verdict` / `rationale`, plus the
 * model + token usage that produced it. Aggregating these weighted scores yields
 * the per-agent factor breakdown the Decision Engine (§3) turns into an explainable
 * recommendation. `agent_runs` has NO updated_at — a run is an immutable audit fact
 * (same principle as workflow_run_steps); it references `interview_id` directly.
 *
 * Config-driven (no ENUMs): agent_type / verdict are code-validated VARCHAR / JSON.
 * Idempotent via information_schema guards. Self-contained — all FK targets
 * (workspaces, interviews) already exist; the lens prompts mirror config/ai_agents.php.
 */
return new class extends Migration {
    /**
     * The nine SYSTEM agents (workspace_id NULL, is_system=1). The eight scoring
     * agents' weights sum to ~100; the aggregator 'decision' agent scores nothing.
     *
     * @var array<int, array{0:string,1:string,2:string,3:float,4:int}>
     *      [key, name, agent_type, weight, sort_order]
     */
    private array $agents = [
        ['hr',            'HR Screening Agent',        'scoring', 10.0, 1],
        ['technical',     'Technical Expert Agent',    'scoring', 25.0, 2],
        ['behavior',      'Behavioural Agent',         'scoring', 15.0, 3],
        ['psychometric',  'Psychometric Agent',        'scoring', 10.0, 4],
        ['communication', 'Communication Agent',       'scoring', 12.0, 5],
        ['language',      'Language Proficiency Agent', 'scoring',  8.0, 6],
        ['culture_fit',   'Culture-Fit Agent',         'scoring', 10.0, 7],
        ['risk',          'Risk & Integrity Agent',    'scoring', 10.0, 8],
        ['decision',      'Decision Aggregator Agent', 'decision', 0.0, 9],
    ];

    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'ai_agents')) {
            $db->unprepared(
                "CREATE TABLE `ai_agents` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `name` VARCHAR(120) NOT NULL,
                    `agent_type` VARCHAR(40) NOT NULL,
                    `description` VARCHAR(500) NULL,
                    `weight` DECIMAL(6,3) NOT NULL DEFAULT 0,
                    `config` JSON NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ai_agents_uuid_unique` (`uuid`),
                    UNIQUE KEY `ai_agents_workspace_key_unique` (`workspace_id`, `key`),
                    KEY `ai_agents_workspace_id_index` (`workspace_id`),
                    KEY `ai_agents_key_index` (`key`),
                    CONSTRAINT `ai_agents_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'agent_runs')) {
            $db->unprepared(
                "CREATE TABLE `agent_runs` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `agent_key` VARCHAR(60) NOT NULL,
                    `agent_type` VARCHAR(40) NULL,
                    `score` DECIMAL(6,3) NULL,
                    `max_score` DECIMAL(6,3) NULL,
                    `confidence` DECIMAL(5,2) NULL,
                    `verdict` JSON NULL,
                    `rationale` TEXT NULL,
                    `model_key` VARCHAR(80) NULL,
                    `prompt_tokens` INT UNSIGNED NULL,
                    `completion_tokens` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `agent_runs_uuid_unique` (`uuid`),
                    KEY `agent_runs_workspace_id_index` (`workspace_id`),
                    KEY `agent_runs_interview_id_index` (`interview_id`),
                    KEY `agent_runs_agent_key_index` (`agent_key`),
                    CONSTRAINT `agent_runs_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `agent_runs_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $this->seedAgents($db);
    }

    public function down(Database $db): void
    {
        foreach (['agent_runs', 'ai_agents'] as $t) {
            $db->unprepared("DROP TABLE IF EXISTS `{$t}`");
        }
    }

    /**
     * Idempotently seed the nine SYSTEM agents (workspace_id NULL, is_system=1).
     * Mirrors the 0035 lookup-seeding pattern: skip rows that already exist so a
     * re-run is harmless.
     */
    private function seedAgents(Database $db): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->agents as [$key, $name, $type, $weight, $sort]) {
            $exists = (int) $db->scalar(
                'SELECT COUNT(*) FROM ai_agents WHERE `key` = ? AND workspace_id IS NULL',
                [$key]
            );
            if ($exists > 0) {
                continue;
            }

            $db->table('ai_agents')->insert([
                'uuid'         => (string) $db->scalar('SELECT UUID()'),
                'workspace_id' => null,
                'key'          => $key,
                'name'         => $name,
                'agent_type'   => $type,
                'description'  => $name . ' — single-responsibility interview lens (docs/51 §2).',
                'weight'       => $weight,
                'config'       => null,
                'is_active'    => 1,
                'is_system'    => 1,
                'sort_order'   => $sort,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
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
