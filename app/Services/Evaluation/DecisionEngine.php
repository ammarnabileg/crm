<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Models\DecisionFactor;
use App\Models\DecisionRecord;
use InvalidArgumentException;

/**
 * The Decision Engine (docs/51 §3) — aggregates per-criterion scores into one
 * explainable decision using an evaluation template's weights (docs/51 §9).
 *
 * `score()` is a PURE function (no DB, no model calls): given the rubric criteria
 * and raw scores it returns the weighted overall, a normalized 0–100 score, a
 * pass/fail vs the threshold, a recommendation band, and a per-criterion factor
 * breakdown (Explainable AI, §15). Normalization is by the SUM of the weights of
 * the criteria that were actually scored, so any weight scale works and a
 * partially-scored interview is judged only on what it covered. `decide()` then
 * persists that result as a DecisionRecord + DecisionFactors.
 *
 * No model is invoked here — scores come from agents/humans upstream; this engine
 * only aggregates, which keeps decisions deterministic, testable and auditable.
 */
final class DecisionEngine
{
    /**
     * Recommendation bands by normalized score (0–100), high→low. Keys resolve to
     * the `recommendation` lookup category (strong_yes…strong_no).
     *
     * @var array<int, array{0: float, 1: string}>
     */
    private const BANDS = [
        [85.0, 'strong_yes'],
        [70.0, 'yes'],
        [50.0, 'neutral'],
        [30.0, 'no'],
        [0.0,  'strong_no'],
    ];

    /**
     * Pure scoring. No side effects.
     *
     * @param array<int, array<string,mixed>> $criteria  Rubric: each needs key,
     *        weight, max (label/min optional).
     * @param array<string, float|int>        $rawScores criterion key => raw score.
     * @param float                           $passThreshold normalized 0–100 cut-off.
     * @return array{overall_score: float, max_score: float, normalized_score: float,
     *               pass_threshold: float, passed: bool, recommendation_key: string,
     *               scored_count: int, factors: array<int, array<string,mixed>>}
     */
    public function score(array $criteria, array $rawScores, float $passThreshold): array
    {
        if ($criteria === []) {
            throw new InvalidArgumentException('Cannot score against an empty rubric.');
        }

        $factors = [];
        $overall = 0.0;       // sum of weighted contributions
        $totalWeight = 0.0;   // sum of weights of SCORED criteria
        $sort = 0;

        foreach ($criteria as $c) {
            $key = (string) ($c['key'] ?? '');
            if ($key === '') {
                throw new InvalidArgumentException('A criterion is missing its key.');
            }
            $weight = (float) ($c['weight'] ?? 0);
            $max = (float) ($c['max'] ?? $c['max_value'] ?? 0);
            $label = (string) ($c['label'] ?? $key);

            // Skip criteria with no provided score — they don't penalize the candidate.
            if (! array_key_exists($key, $rawScores)) {
                continue;
            }

            $raw = (float) $rawScores[$key];
            // Clamp the raw score into [0, max].
            $clamped = $max > 0 ? max(0.0, min($raw, $max)) : 0.0;
            $pct = $max > 0 ? $clamped / $max : 0.0;
            $weighted = round($pct * $weight, 4);

            $overall += $weighted;
            $totalWeight += $weight;

            $factors[] = [
                'criterion_key'   => $key,
                'criterion_label' => $label,
                'weight'          => $weight,
                'raw_score'       => $clamped,
                'max_score'       => $max,
                'weighted_score'  => $weighted,
                'rationale'       => sprintf(
                    '%s: scored %s/%s (%.0f%%) × weight %s = %.2f',
                    $label,
                    rtrim(rtrim(number_format($clamped, 2), '0'), '.'),
                    rtrim(rtrim(number_format($max, 2), '0'), '.'),
                    $pct * 100,
                    rtrim(rtrim(number_format($weight, 2), '0'), '.'),
                    $weighted
                ),
                'sort_order'      => $sort++,
            ];
        }

        $normalized = $totalWeight > 0 ? round(($overall / $totalWeight) * 100, 2) : 0.0;
        $passed = $normalized >= $passThreshold;

        return [
            'overall_score'      => round($overall, 4),
            'max_score'          => round($totalWeight, 4),
            'normalized_score'   => $normalized,
            'pass_threshold'     => $passThreshold,
            'passed'             => $passed,
            'recommendation_key' => $this->recommendationKey($normalized),
            'scored_count'       => count($factors),
            'factors'            => $factors,
        ];
    }

    /** Map a normalized 0–100 score to a recommendation band key. */
    public function recommendationKey(float $normalized): string
    {
        foreach (self::BANDS as [$floor, $key]) {
            if ($normalized >= $floor) {
                return $key;
            }
        }

        return 'strong_no';
    }

    /**
     * Score and persist a decision (DecisionRecord + DecisionFactors). Returns the
     * created DecisionRecord. Tenant + uuid are stamped by the Model layer.
     *
     * @param array<int, array<string,mixed>> $criteria
     * @param array<string, float|int>        $rawScores
     * @param array{interview_id?: int|null, application_id?: int|null,
     *              form_version_id?: int|null, is_ai?: bool, decided_by?: int|null,
     *              confidence?: float|null, summary?: string|null,
     *              meta?: array<string,mixed>} $context
     */
    public function decide(array $criteria, array $rawScores, float $passThreshold, array $context = []): DecisionRecord
    {
        $result = $this->score($criteria, $rawScores, $passThreshold);
        $recommendationId = lookup_id('recommendation', $result['recommendation_key']);

        $record = DecisionRecord::create([
            'interview_id'      => $context['interview_id'] ?? null,
            'application_id'    => $context['application_id'] ?? null,
            'form_version_id'   => $context['form_version_id'] ?? null,
            'overall_score'     => $result['overall_score'],
            'max_score'         => $result['max_score'],
            'normalized_score'  => $result['normalized_score'],
            'pass_threshold'    => $result['pass_threshold'],
            'passed'            => $result['passed'] ? 1 : 0,
            'recommendation_id' => $recommendationId,
            'is_ai'             => ($context['is_ai'] ?? false) ? 1 : 0,
            'confidence'        => $context['confidence'] ?? null,
            'summary'           => $context['summary'] ?? null,
            'meta'              => isset($context['meta'])
                ? json_encode($context['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'decided_by'        => $context['decided_by'] ?? null,
            'decided_at'        => now(),
        ]);

        $decisionId = (int) $record->getKey();
        foreach ($result['factors'] as $factor) {
            DecisionFactor::create([
                'decision_id'     => $decisionId,
                'criterion_key'   => $factor['criterion_key'],
                'criterion_label' => $factor['criterion_label'],
                'weight'          => $factor['weight'],
                'raw_score'       => $factor['raw_score'],
                'max_score'       => $factor['max_score'],
                'weighted_score'  => $factor['weighted_score'],
                'rationale'       => $factor['rationale'],
                'sort_order'      => $factor['sort_order'],
                'created_at'      => now(),
            ]);
        }

        return $record;
    }
}
