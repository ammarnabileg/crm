<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Models\EvaluationForm;
use App\Models\EvaluationFormField;
use App\Models\EvaluationFormVersion;
use RuntimeException;

/**
 * Evaluation Template service (docs/51 §9, AI Interview Engine P2).
 *
 * An evaluation template IS the existing configurable scorecard: an
 * `evaluation_forms` rubric + its weighted `evaluation_form_fields` criteria
 * (see migration 0034's design note on why P2 reuses these rather than building
 * parallel tables). This service:
 *  - validates a rubric's criteria (keys, weights, bounds);
 *  - PUBLISHES an immutable version snapshot the Decision Engine scores against;
 *  - returns the active snapshot for a form;
 *  - INSTANTIATES a starter rubric from the role-family library
 *    (config/evaluation_templates.php) into the current tenant.
 *
 * All operations are tenant-scoped through the Model layer (fail-closed).
 */
final class EvaluationTemplate
{
    /**
     * Validate a set of criteria definitions. Returns a list of human-readable
     * issues — an empty list means the rubric is valid.
     *
     * @param array<int, array<string,mixed>> $criteria
     * @return string[]
     */
    public function validateCriteria(array $criteria): array
    {
        $issues = [];
        if ($criteria === []) {
            return ['A template needs at least one criterion.'];
        }

        $seen = [];
        foreach ($criteria as $i => $c) {
            $key = (string) ($c['key'] ?? '');
            if ($key === '') {
                $issues[] = "Criterion #{$i} is missing a key.";
            } elseif (isset($seen[$key])) {
                $issues[] = "Duplicate criterion key '{$key}'.";
            } else {
                $seen[$key] = true;
            }

            $weight = (float) ($c['weight'] ?? 0);
            if ($weight < 0) {
                $issues[] = "Criterion '{$key}' has a negative weight.";
            }

            $max = (float) ($c['max'] ?? $c['max_value'] ?? 0);
            $min = (float) ($c['min'] ?? $c['min_value'] ?? 0);
            if ($max <= 0) {
                $issues[] = "Criterion '{$key}' must have a positive max.";
            }
            if ($min < 0) {
                $issues[] = "Criterion '{$key}' has a negative min.";
            }
            if ($max > 0 && $min >= $max) {
                $issues[] = "Criterion '{$key}' min must be less than max.";
            }
        }

        $totalWeight = array_sum(array_map(static fn ($c): float => (float) ($c['weight'] ?? 0), $criteria));
        if ($totalWeight <= 0) {
            $issues[] = 'Total criterion weight must be greater than zero.';
        }

        return $issues;
    }

    /**
     * The canonical criteria array derived from a form's live fields.
     *
     * @return array<int, array<string,mixed>>
     */
    public function criteriaFromForm(EvaluationForm $form): array
    {
        $out = [];
        foreach ($form->fields() as $row) {
            $out[] = [
                'key'    => (string) $row['key'],
                'label'  => (string) $row['label'],
                'weight' => (float) $row['weight'],
                'max'    => (float) ($row['max_value'] ?? 0),
                'min'    => (float) ($row['min_value'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Publish an immutable snapshot of a form + its criteria as the new active
     * version. Deactivates any prior active version and bumps the form's version
     * counter. Throws if the form is missing or its rubric is invalid.
     */
    public function publish(int $formId, ?int $userId = null, ?string $notes = null): EvaluationFormVersion
    {
        $form = EvaluationForm::find($formId);
        if ($form === null) {
            throw new RuntimeException("Evaluation form {$formId} not found in this workspace.");
        }

        $criteria = $this->criteriaFromForm($form);
        $issues = $this->validateCriteria($criteria);
        if ($issues !== []) {
            throw new RuntimeException('Cannot publish: ' . implode(' ', $issues));
        }

        // Next version number for this form (tenant-scoped).
        $latest = EvaluationFormVersion::query()
            ->where('form_id', '=', $formId)
            ->orderBy('version', 'desc')
            ->first();
        $next = $latest !== null ? ((int) $latest['version']) + 1 : 1;

        // Deactivate the previous active version(s).
        EvaluationFormVersion::query()
            ->where('form_id', '=', $formId)
            ->where('is_active', '=', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $snapshot = [
            'form' => [
                'name'           => (string) $form->name,
                'slug'           => (string) $form->slug,
                'pass_threshold' => (float) $form->pass_threshold,
                'max_score'      => $form->max_score !== null ? (float) $form->max_score : null,
            ],
            'pass_threshold' => (float) $form->pass_threshold,
            'criteria'       => $criteria,
        ];

        $version = EvaluationFormVersion::create([
            'form_id'      => $formId,
            'version'      => $next,
            'snapshot'     => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes'        => $notes,
            'is_active'    => 1,
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        // Keep the form's version counter in step with the published snapshot.
        $form->update(['version' => $next]);

        return $version;
    }

    /**
     * The active published snapshot (criteria + meta) for a form, or null if it has
     * never been published.
     *
     * @return array<string,mixed>|null
     */
    public function activeSnapshot(int $formId): ?array
    {
        $version = EvaluationFormVersion::activeFor($formId);
        if ($version === null) {
            return null;
        }

        $snapshot = $version->snapshot; // 'array' cast decodes the JSON

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Instantiate a starter rubric from the role-family library into the current
     * tenant as an editable `evaluation_forms` + `evaluation_form_fields` rubric.
     * Does NOT publish — call publish() to freeze a version for scoring.
     */
    public function instantiateFromLibrary(string $libraryKey, ?int $userId = null): EvaluationForm
    {
        $definition = config('evaluation_templates.library.' . $libraryKey);
        if (! is_array($definition)) {
            throw new RuntimeException("Unknown evaluation template '{$libraryKey}'.");
        }

        $fieldTypeKey = (string) config('evaluation_templates.field_type', 'rating');
        $fieldTypeId = lookup_id('evaluation_field_type', $fieldTypeKey);

        $form = EvaluationForm::create([
            'name'           => (string) $definition['name'],
            'slug'           => $this->uniqueSlug((string) $definition['name']),
            'description'    => $definition['description'] ?? null,
            'max_score'      => 100,
            'pass_threshold' => (float) ($definition['pass_threshold'] ?? 60),
            'version'        => 0,
            'is_active'      => 1,
            'is_default'     => 0,
            'created_by'     => $userId,
        ]);

        $formId = (int) $form->getKey();
        $sort = 0;
        foreach ((array) ($definition['criteria'] ?? []) as $criterion) {
            EvaluationFormField::create([
                'form_id'       => $formId,
                'label'         => (string) $criterion['label'],
                'key'           => (string) $criterion['key'],
                'field_type_id' => $fieldTypeId,
                'weight'        => (float) ($criterion['weight'] ?? 0),
                'max_value'     => (float) ($criterion['max'] ?? 5),
                'min_value'     => (float) ($criterion['min'] ?? 0),
                'is_required'   => 1,
                'sort_order'    => $sort++,
            ]);
        }

        return $form;
    }

    /** A workspace-unique slug derived from $name. */
    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        if ($base === '') {
            $base = 'template';
        }

        $slug = $base;
        $n = 2;
        while (EvaluationForm::withTrashed()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
