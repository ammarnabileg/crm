<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningFeedback;
use InvalidArgumentException;

/**
 * The Continuous Learning Engine (docs/51 §18). It CAPTURES the signal the platform
 * will later learn from — human corrections, real-world outcomes, and system
 * observations — and AGGREGATES it within a tenant. No model is retrained here: P10
 * is deliberately future-ready (capture + aggregate only), so that when learning is
 * switched on it has a clean, auditable, tenant-isolated corpus to draw on.
 *
 * Tenant isolation is absolute: feedback is stamped with the active workspace by the
 * Model layer, and every read here is scoped to the active tenant — a tenant never
 * learns from another tenant's data.
 */
final class LearningEngine
{
    /** Where a feedback signal came from (config-driven, no ENUM). */
    public const SOURCES = ['human', 'outcome', 'system'];

    /**
     * Capture one feedback signal.
     *
     * @param array{interview_id?: int|null, decision_id?: int|null, source: string,
     *              rating?: float|int|null, correction?: array<string,mixed>|null,
     *              notes?: string|null, created_by?: int|null} $attrs
     *
     * @throws InvalidArgumentException When source is not one of self::SOURCES.
     */
    public function recordFeedback(array $attrs): LearningFeedback
    {
        $source = (string) ($attrs['source'] ?? '');
        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unsupported feedback source [{$source}].");
        }

        // The structured correction is an array on the model, so it must be encoded
        // on write (Model casts are read-only).
        $correction = $attrs['correction'] ?? null;

        return LearningFeedback::create([
            'interview_id' => $attrs['interview_id'] ?? null,
            'decision_id'  => $attrs['decision_id'] ?? null,
            'source'       => $source,
            'rating'       => isset($attrs['rating']) ? (float) $attrs['rating'] : null,
            'correction'   => is_array($correction) && $correction !== []
                ? json_encode($correction, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'notes'        => $attrs['notes'] ?? null,
            'created_by'   => $attrs['created_by'] ?? null,
        ]);
    }

    /**
     * All feedback captured for a decision (oldest first), tenant-scoped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forDecision(int $decisionId): array
    {
        return app('db')->table('learning_feedback')
            ->where('decision_id', '=', $decisionId)
            ->where('workspace_id', '=', tenant()->id())
            ->orderBy('id')
            ->get();
    }

    /**
     * Tenant-scoped aggregate of the captured signal — the summary the engine will
     * later learn from (within the tenant boundary only).
     *
     * @return array{feedback_count: int, avg_rating: float|null,
     *               by_source: array<string, int>}
     */
    public function stats(): array
    {
        $rows = app('db')->table('learning_feedback')
            ->where('workspace_id', '=', tenant()->id())
            ->select('source', 'rating')
            ->get();

        $bySource = array_fill_keys(self::SOURCES, 0);
        $ratingSum = 0.0;
        $ratingCount = 0;

        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? '');
            if (! array_key_exists($source, $bySource)) {
                $bySource[$source] = 0;
            }
            $bySource[$source]++;

            if ($row['rating'] !== null) {
                $ratingSum += (float) $row['rating'];
                $ratingCount++;
            }
        }

        return [
            'feedback_count' => count($rows),
            'avg_rating'     => $ratingCount > 0 ? round($ratingSum / $ratingCount, 2) : null,
            'by_source'      => $bySource,
        ];
    }
}
