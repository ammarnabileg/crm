<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The Runtime's judgement of whether a {@see MergedResult} is trustworthy enough to act on.
 *
 * Produced by the {@see \Nizam\Runtime\Orchestration\ConfidenceEvaluator}, an assessment carries a
 * normalized {@see self::score()} in [0, 1], a human {@see self::rationale()} explaining the score, and
 * two independent hints the Manager uses to decide: {@see self::needsRetry()} (the work should be tried
 * again) and {@see self::needsMoreWorkers()} (more workers should be dispatched to raise confidence).
 * The evaluator scores; the manager decides — the assessment expresses no decision itself. Being a value
 * object it is immutable and self-validating.
 */
final class ConfidenceAssessment implements ValueObject
{
    /**
     * @param float  $score            The normalized confidence in the merged result, in [0, 1].
     * @param string $rationale        A human-readable explanation of the score.
     * @param bool   $needsRetry       Whether the work should be retried to raise confidence.
     * @param bool   $needsMoreWorkers Whether more workers should be dispatched to raise confidence.
     */
    public function __construct(
        private readonly float $score,
        private readonly string $rationale,
        private readonly bool $needsRetry,
        private readonly bool $needsMoreWorkers,
    ) {
        Assert::that(
            $score >= 0.0 && $score <= 1.0,
            'A confidence score must be within the inclusive range [0, 1].',
        );
        Assert::notEmpty($rationale, 'A confidence assessment must carry a non-empty rationale.');
    }

    /**
     * The normalized confidence in the merged result, in [0, 1].
     */
    public function score(): float
    {
        return $this->score;
    }

    /**
     * The human-readable explanation of the score.
     */
    public function rationale(): string
    {
        return $this->rationale;
    }

    /**
     * Whether the work should be retried to raise confidence.
     */
    public function needsRetry(): bool
    {
        return $this->needsRetry;
    }

    /**
     * Whether more workers should be dispatched to raise confidence.
     */
    public function needsMoreWorkers(): bool
    {
        return $this->needsMoreWorkers;
    }

    /**
     * Whether the assessment clears the given acceptance threshold and asks for no further work.
     *
     * @param float $threshold The minimum score, in [0, 1], considered acceptable.
     */
    public function isAcceptable(float $threshold): bool
    {
        return $this->score >= $threshold && !$this->needsRetry && !$this->needsMoreWorkers;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->score === $this->score
            && $other->rationale === $this->rationale
            && $other->needsRetry === $this->needsRetry
            && $other->needsMoreWorkers === $this->needsMoreWorkers;
    }

    /**
     * A scalar-only representation suitable for persistence and read models.
     *
     * @return array{score: float, rationale: string, needsRetry: bool, needsMoreWorkers: bool}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'rationale' => $this->rationale,
            'needsRetry' => $this->needsRetry,
            'needsMoreWorkers' => $this->needsMoreWorkers,
        ];
    }
}
