<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration;

use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Orchestration\ValueObject\ConfidenceAssessment;
use Nizam\Runtime\Orchestration\ValueObject\MergedResult;

/**
 * Scores a {@see MergedResult} into a {@see ConfidenceAssessment} the manager uses to decide.
 *
 * This service is a pure, deterministic function of the merged result and its two configured thresholds
 * — no I/O, no clock, no randomness. It takes the merged aggregate confidence as the base score, then
 * derives two independent hints for the manager: the work {@see ConfidenceAssessment::needsRetry()} when
 * any contributing result reported an error (an error signals the work did not complete cleanly and
 * should be tried again), and it {@see ConfidenceAssessment::needsMoreWorkers()} when the score is below
 * the "more workers" threshold *and* no error is present (low but clean confidence is better addressed
 * by adding capacity than by retrying the same work). The rationale explains the score in plain terms.
 * The evaluator only scores and hints; the manager decides. It is a spec-mandated step on the timeline.
 */
final class ConfidenceEvaluator
{
    /**
     * The default score at or above which a clean result is considered acceptable.
     */
    public const float DEFAULT_ACCEPT_THRESHOLD = 0.75;

    /**
     * The default score below which more workers are suggested for a clean result.
     */
    public const float DEFAULT_MORE_WORKERS_THRESHOLD = 0.5;

    /**
     * @param float $acceptThreshold      The score at or above which a clean result is acceptable, in [0, 1].
     * @param float $moreWorkersThreshold The score below which more workers are suggested, in [0, 1].
     */
    public function __construct(
        private readonly float $acceptThreshold = self::DEFAULT_ACCEPT_THRESHOLD,
        private readonly float $moreWorkersThreshold = self::DEFAULT_MORE_WORKERS_THRESHOLD,
    ) {
        Assert::that(
            $acceptThreshold >= 0.0 && $acceptThreshold <= 1.0,
            'The accept threshold must be within [0, 1].',
        );
        Assert::that(
            $moreWorkersThreshold >= 0.0 && $moreWorkersThreshold <= 1.0,
            'The more-workers threshold must be within [0, 1].',
        );
        Assert::that(
            $moreWorkersThreshold <= $acceptThreshold,
            'The more-workers threshold must not exceed the accept threshold.',
        );
    }

    /**
     * Evaluate a merged result into a confidence assessment.
     *
     * @param MergedResult $merged The merged worker output to score.
     *
     * @return ConfidenceAssessment The score, rationale, and retry/more-workers hints.
     */
    public function evaluate(MergedResult $merged): ConfidenceAssessment
    {
        $score = $merged->aggregateConfidence();
        $hasErrors = $merged->hasErrors();

        $needsRetry = $hasErrors;
        $needsMoreWorkers = !$hasErrors && $score < $this->moreWorkersThreshold;

        return new ConfidenceAssessment(
            score: $score,
            rationale: $this->rationale($merged, $score, $needsRetry, $needsMoreWorkers),
            needsRetry: $needsRetry,
            needsMoreWorkers: $needsMoreWorkers,
        );
    }

    /**
     * Build a plain-language rationale for the assessment.
     */
    private function rationale(MergedResult $merged, float $score, bool $needsRetry, bool $needsMoreWorkers): string
    {
        $percent = (int) round($score * 100);
        $base = sprintf(
            'Aggregate confidence %d%% across %d worker result(s).',
            $percent,
            $merged->resultCount(),
        );

        if ($needsRetry) {
            return $base . sprintf(
                ' %d error(s) reported; the work should be retried.',
                count($merged->errors()),
            );
        }

        if ($needsMoreWorkers) {
            return $base . sprintf(
                ' Below the %d%% more-workers threshold; additional workers are recommended.',
                (int) round($this->moreWorkersThreshold * 100),
            );
        }

        if ($score >= $this->acceptThreshold) {
            return $base . sprintf(
                ' At or above the %d%% acceptance threshold.',
                (int) round($this->acceptThreshold * 100),
            );
        }

        return $base . ' Within the acceptable band; no further work required.';
    }
}
