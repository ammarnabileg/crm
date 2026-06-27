<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintSection;
use App\Models\BlueprintSectionRule;
use App\Models\InterviewBlueprint;
use RuntimeException;

/**
 * Blueprint Library service (docs/51 §6, §11 — AI Interview Engine).
 *
 * Exposes the role-family starter blueprints in config/blueprints.php and
 * INSTANTIATES one into the current tenant as an editable
 * `interview_blueprints` + `blueprint_sections` + `blueprint_section_rules` tree.
 * Each section's question_strategy / scoring_rules / follow_up_rules /
 * expected_skills / expected_behaviors — plus the blueprint-level
 * evaluation_criteria — are materialized as typed `blueprint_section_rules` rows
 * (rule_type: question_strategy | scoring | follow_up | expected_skill |
 * expected_behavior | evaluation_criteria), so the interview runtime reads a single
 * uniform rule table.
 *
 * Instantiation does NOT publish — call App\Services\Blueprint\BlueprintEngine::
 * publish() to freeze a version for the runtime. All writes are tenant-scoped
 * through the Model layer (fail-closed).
 */
final class BlueprintLibrary
{
    /** @return string[] The available library keys. */
    public function keys(): array
    {
        $library = config('blueprints.library', []);

        return is_array($library) ? array_keys($library) : [];
    }

    /**
     * The raw library definition for a key, or null if it does not exist.
     *
     * @return array<string,mixed>|null
     */
    public function definition(string $key): ?array
    {
        $definition = config('blueprints.library.' . $key);

        return is_array($definition) ? $definition : null;
    }

    /**
     * Instantiate a starter blueprint from the library into the current tenant as an
     * editable blueprint with its sections and per-section rules. Throws for an
     * unknown key.
     */
    public function instantiateFromLibrary(string $key, ?int $userId = null): InterviewBlueprint
    {
        $definition = $this->definition($key);
        if ($definition === null) {
            throw new RuntimeException("Unknown blueprint '{$key}'.");
        }

        $blueprint = InterviewBlueprint::create([
            'name'        => (string) $definition['name'],
            'slug'        => $this->uniqueSlug((string) $definition['name']),
            'role_family' => (string) ($definition['role_family'] ?? 'general'),
            'description' => $definition['description'] ?? null,
            'is_active'   => 1,
            'version'     => 0,
            'created_by'  => $userId,
        ]);

        $blueprintId = (int) $blueprint->getKey();

        // Blueprint-level evaluation criteria are recorded once, attached to the
        // first section (or, if there are no sections, skipped).
        $evaluationCriteria = (array) ($definition['evaluation_criteria'] ?? []);

        $sectionSort = 0;
        foreach ((array) ($definition['sections'] ?? []) as $sectionDef) {
            $section = BlueprintSection::create([
                'blueprint_id' => $blueprintId,
                'key'          => (string) $sectionDef['key'],
                'title'        => (string) $sectionDef['title'],
                'objective'    => $sectionDef['objective'] ?? null,
                'weight'       => (float) ($sectionDef['weight'] ?? 0),
                'difficulty'   => $sectionDef['difficulty'] ?? null,
                'config'       => null,
                'sort_order'   => $sectionSort,
            ]);

            $sectionId = (int) $section->getKey();
            $this->materializeRules($sectionId, $sectionDef);

            // Attach the blueprint-level evaluation criteria to the first section so
            // they live in the same uniform rule table the runtime reads.
            if ($sectionSort === 0 && $evaluationCriteria !== []) {
                $this->createRules($sectionId, 'evaluation_criteria', $evaluationCriteria, 100);
            }

            $sectionSort++;
        }

        return $blueprint;
    }

    /**
     * Materialize a section definition's strategy/scoring/follow-up/skill/behavior
     * lists into typed `blueprint_section_rules` rows.
     *
     * @param array<string,mixed> $sectionDef
     */
    private function materializeRules(int $sectionId, array $sectionDef): void
    {
        $strategy = (string) ($sectionDef['question_strategy'] ?? '');
        if ($strategy !== '') {
            $this->createRules($sectionId, 'question_strategy', [$strategy], 0);
        }

        $this->createRules($sectionId, 'scoring', (array) ($sectionDef['scoring_rules'] ?? []), 10);
        $this->createRules($sectionId, 'follow_up', (array) ($sectionDef['follow_up_rules'] ?? []), 20);
        $this->createRules($sectionId, 'expected_skill', (array) ($sectionDef['expected_skills'] ?? []), 30);
        $this->createRules($sectionId, 'expected_behavior', (array) ($sectionDef['expected_behaviors'] ?? []), 40);
    }

    /**
     * Create one rule row per value, each payload carrying the rule type and value.
     *
     * @param array<int,mixed> $values
     */
    private function createRules(int $sectionId, string $ruleType, array $values, int $sortBase): void
    {
        $i = 0;
        foreach ($values as $value) {
            BlueprintSectionRule::create([
                'section_id' => $sectionId,
                'rule_type'  => $ruleType,
                'payload'    => json_encode(
                    ['type' => $ruleType, 'value' => $value],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                'sort_order' => $sortBase + $i,
            ]);
            $i++;
        }
    }

    /** A workspace-unique slug derived from $name. */
    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        if ($base === '') {
            $base = 'blueprint';
        }

        $slug = $base;
        $n = 2;
        while (InterviewBlueprint::withTrashed()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
