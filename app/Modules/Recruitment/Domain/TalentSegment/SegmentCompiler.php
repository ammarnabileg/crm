<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\TalentSegment;

use InvalidArgumentException;

/**
 * Compiles a set of {@see SegmentRule}s into correlated SQL predicates over the
 * outer `candidate_profiles cp` row. PURE: no database, no clock of its own —
 * "now" is injected — so the exact SQL/bindings/labels a segment produces are
 * unit-testable without a live MySQL.
 *
 * Every rule becomes a single self-contained EXISTS/scalar predicate correlated
 * on `cp.workspace_id` + `cp.user_id`; the {@see SegmentService} reuses each
 * predicate twice (once in the SELECT list to explain the match, once in WHERE to
 * filter) and appends the bindings in that exact textual order — which is why
 * each rule keeps its own ordered binding list here.
 */
final class SegmentCompiler
{
    /**
     * @param  list<SegmentRule>  $rules
     * @param  string  $matchType  'all' (AND) or 'any' (OR)
     * @param  string  $nowUtc     current UTC time 'Y-m-d H:i:s' (injected for purity)
     * @return array{glue: string, predicates: list<array{sql: string, bindings: list<string>, label: string}>}
     */
    public function compile(array $rules, string $matchType, string $nowUtc): array
    {
        $predicates = [];
        foreach ($rules as $rule) {
            $predicates[] = $this->predicate($rule, $nowUtc);
        }

        return [
            'glue' => $matchType === 'any' ? ' OR ' : ' AND ',
            'predicates' => $predicates,
        ];
    }

    /**
     * @return array{sql: string, bindings: list<string>, label: string}
     */
    private function predicate(SegmentRule $rule, string $nowUtc): array
    {
        $value = trim($rule->value);

        return match ($rule->field) {
            SegmentField::SKILL => $this->fieldMatch('skills', $value, 'Skill'),
            SegmentField::LANGUAGE => $this->fieldMatch('languages', $value, 'Language'),
            SegmentField::SENIORITY => $this->fieldMatch('seniority', $value, 'Seniority'),
            SegmentField::AVAILABLE => $this->available(),
            SegmentField::MIN_SCORE => $this->minScore($value),
            SegmentField::LAST_INTERVIEW_MONTHS => $this->lastInterview($value, $nowUtc),
            SegmentField::STATUS => $this->status($value),
            default => throw new InvalidArgumentException("Uncompilable segment field: {$rule->field}"),
        };
    }

    /**
     * A normalised profile field (skills/languages/seniority) matched on the
     * `candidate_profile_fields` table, with a fallback to the legacy
     * `candidate_profiles.details` JSON so pre-migration data still matches.
     *
     * @return array{sql: string, bindings: list<string>, label: string}
     */
    private function fieldMatch(string $fieldKey, string $value, string $label): array
    {
        $like = '%' . $value . '%';

        return [
            'sql' => '(EXISTS (SELECT 1 FROM candidate_profile_fields f'
                . ' WHERE f.workspace_id = cp.workspace_id AND f.user_id = cp.user_id'
                . " AND f.field_key = '{$fieldKey}' AND f.field_value LIKE ?)"
                . ' OR CAST(cp.details AS CHAR) LIKE ?)',
            'bindings' => [$like, $like],
            'label' => $label . ': ' . $value,
        ];
    }

    /** @return array{sql: string, bindings: list<string>, label: string} */
    private function available(): array
    {
        return [
            'sql' => "EXISTS (SELECT 1 FROM candidate_profile_fields f"
                . ' WHERE f.workspace_id = cp.workspace_id AND f.user_id = cp.user_id'
                . " AND f.field_key IN ('availability', 'available') AND f.field_value <> ''"
                . " AND LOWER(f.field_value) NOT IN ('no', 'none', 'false', '0', 'unavailable', 'not available'))",
            'bindings' => [],
            'label' => 'Available',
        ];
    }

    /** @return array{sql: string, bindings: list<string>, label: string} */
    private function minScore(string $value): array
    {
        $score = (string) (int) $value;

        return [
            'sql' => '((SELECT MAX(ca.fit_score) FROM candidate_assessments ca'
                . ' WHERE ca.workspace_id = cp.workspace_id AND ca.candidate_user_id = cp.user_id) >= ?)',
            'bindings' => [$score],
            'label' => 'Score ≥ ' . $score,
        ];
    }

    /** @return array{sql: string, bindings: list<string>, label: string} */
    private function lastInterview(string $value, string $nowUtc): array
    {
        $months = max(1, (int) $value);
        $threshold = gmdate('Y-m-d H:i:s', (int) strtotime($nowUtc . ' -' . $months . ' months'));

        return [
            'sql' => "EXISTS (SELECT 1 FROM interviews i"
                . ' WHERE i.workspace_id = cp.workspace_id AND i.candidate_user_id = cp.user_id'
                . " AND i.status = 'completed' AND i.deleted_at IS NULL"
                . ' AND COALESCE(i.completed_at, i.created_at) >= ?)',
            'bindings' => [$threshold],
            'label' => 'Interviewed ≤ ' . $months . ' month' . ($months === 1 ? '' : 's') . ' ago',
        ];
    }

    /** @return array{sql: string, bindings: list<string>, label: string} */
    private function status(string $value): array
    {
        return [
            'sql' => 'EXISTS (SELECT 1 FROM applications a'
                . ' WHERE a.workspace_id = cp.workspace_id AND a.user_id = cp.user_id'
                . ' AND a.deleted_at IS NULL AND a.status = ?)',
            'bindings' => [$value],
            'label' => 'Status: ' . $value,
        ];
    }
}
