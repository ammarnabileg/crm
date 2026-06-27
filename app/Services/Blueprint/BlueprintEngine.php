<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Models\BlueprintSection;
use App\Models\BlueprintVersion;
use App\Models\InterviewBlueprint;
use RuntimeException;

/**
 * Blueprint Engine service (docs/51 §6, §11 — AI Interview Engine).
 *
 * PUBLISHES an immutable JSON snapshot of a blueprint — its header plus every
 * section and each section's typed rules — as the new active
 * `blueprint_versions` row, deactivating any prior active version and bumping the
 * blueprint's version counter (exactly the Evaluation Template / Workflow
 * versioning pattern of migrations 0034/0035). The interview runtime always
 * executes against this frozen snapshot, so live edits never rewrite history.
 *
 * Also exposes the active snapshot for a blueprint. All operations are
 * tenant-scoped through the Model layer (fail-closed).
 */
final class BlueprintEngine
{
    /**
     * Publish an immutable snapshot of a blueprint (header + sections + rules) as
     * the new active version. Throws if the blueprint is missing or has no sections.
     */
    public function publish(int $blueprintId, ?int $userId = null, ?string $notes = null): BlueprintVersion
    {
        $blueprint = InterviewBlueprint::find($blueprintId);
        if ($blueprint === null) {
            throw new RuntimeException("Blueprint {$blueprintId} not found in this workspace.");
        }

        $sections = $this->sectionsSnapshot($blueprintId);
        if ($sections === []) {
            throw new RuntimeException('Cannot publish a blueprint with no sections.');
        }

        $snapshot = [
            'blueprint' => [
                'name'        => (string) $blueprint->name,
                'slug'        => (string) $blueprint->slug,
                'role_family' => (string) $blueprint->role_family,
                'description' => $blueprint->description !== null ? (string) $blueprint->description : null,
            ],
            'sections' => $sections,
        ];

        // Next version number for this blueprint (tenant-scoped).
        $latest = BlueprintVersion::query()
            ->where('blueprint_id', '=', $blueprintId)
            ->orderBy('version', 'desc')
            ->first();
        $next = $latest !== null ? ((int) $latest['version']) + 1 : 1;

        // Deactivate the previous active version(s).
        BlueprintVersion::query()
            ->where('blueprint_id', '=', $blueprintId)
            ->where('is_active', '=', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $version = BlueprintVersion::create([
            'blueprint_id' => $blueprintId,
            'version'      => $next,
            'snapshot'     => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes'        => $notes,
            'is_active'    => 1,
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        // Keep the blueprint's version counter in step with the published snapshot.
        $blueprint->update(['version' => $next]);

        return $version;
    }

    /**
     * The active published snapshot (blueprint + sections + rules) for a blueprint,
     * or null if it has never been published.
     *
     * @return array<string,mixed>|null
     */
    public function activeSnapshot(int $blueprintId): ?array
    {
        $version = BlueprintVersion::activeFor($blueprintId);
        if ($version === null) {
            return null;
        }

        $snapshot = $version->snapshot; // 'array' cast decodes the JSON

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Build the ordered section list (each with its decoded rules) for a blueprint's
     * live definition.
     *
     * @return array<int, array<string,mixed>>
     */
    private function sectionsSnapshot(int $blueprintId): array
    {
        $out = [];
        foreach (BlueprintSection::query()
            ->where('blueprint_id', '=', $blueprintId)
            ->orderBy('sort_order')
            ->get() as $row) {
            $section = BlueprintSection::hydrate($row);
            $sectionId = (int) $section->getKey();

            $config = $row['config'] ?? null;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }

            $out[] = [
                'key'        => (string) $row['key'],
                'title'      => (string) $row['title'],
                'objective'  => $row['objective'] !== null ? (string) $row['objective'] : null,
                'weight'     => (float) $row['weight'],
                'difficulty' => $row['difficulty'] !== null ? (string) $row['difficulty'] : null,
                'config'     => $config,
                'rules'      => $this->rulesSnapshot($section->rules()),
            ];
        }

        return $out;
    }

    /**
     * Normalize a section's rule rows into snapshot shape (rule_type + decoded
     * payload, ordered).
     *
     * @param array<int, array<string,mixed>> $ruleRows
     * @return array<int, array<string,mixed>>
     */
    private function rulesSnapshot(array $ruleRows): array
    {
        $out = [];
        foreach ($ruleRows as $row) {
            $payload = $row['payload'] ?? null;
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            $out[] = [
                'rule_type' => (string) $row['rule_type'],
                'payload'   => $payload,
            ];
        }

        return $out;
    }
}
