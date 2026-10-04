<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * The unified Candidate Health Score — one 0-100 number composed of the signals
 * the platform has ALREADY computed (no fresh AI spend): Job Match (the heaviest),
 * Resume Quality, Experience, Skills, Learning, Certifications, Interview Score,
 * Human Evaluation, Social Credibility (a small weight), and Activity.
 *
 * Pure and deterministic. Each component arrives as a 0-100 value or null when the
 * data for it does not exist yet; the weights of only the AVAILABLE components are
 * re-normalised, so a candidate with no interview yet is still scored fairly on
 * what is known. Bands mirror the AI recommendation bands (82+/68-81/50-67/<50).
 */
final class CandidateHealthScore
{
    /**
     * Ordered component catalog: key => [label, weight]. Weights sum to 1.0; Job
     * Match is the largest, Social Credibility a deliberately small helper.
     *
     * @var array<string, array{label: string, weight: float}>
     */
    private const COMPONENTS = [
        'job_match' => ['label' => 'Job Match', 'weight' => 0.28],
        'resume_quality' => ['label' => 'Resume Quality', 'weight' => 0.08],
        'experience' => ['label' => 'Experience', 'weight' => 0.08],
        'skills' => ['label' => 'Skills', 'weight' => 0.10],
        'learning' => ['label' => 'Learning', 'weight' => 0.06],
        'certifications' => ['label' => 'Certifications', 'weight' => 0.04],
        'interview_score' => ['label' => 'Interview Score', 'weight' => 0.14],
        'human_evaluation' => ['label' => 'Human Evaluation', 'weight' => 0.14],
        'social_credibility' => ['label' => 'Social Credibility', 'weight' => 0.04],
        'activity' => ['label' => 'Activity', 'weight' => 0.04],
    ];

    /**
     * @param  array<string, float|int|null>  $components  key => 0-100 or null (unavailable)
     * @return array{
     *     score: int, band: string,
     *     components: list<array{key: string, label: string, value: int|null, weight: int, contributing: bool}>
     * }
     */
    public static function compute(array $components): array
    {
        $availableWeight = 0.0;
        foreach (self::COMPONENTS as $key => $meta) {
            if (self::valueOf($components, $key) !== null) {
                $availableWeight += $meta['weight'];
            }
        }

        $weighted = 0.0;
        $rows = [];
        foreach (self::COMPONENTS as $key => $meta) {
            $value = self::valueOf($components, $key);
            $contributing = $value !== null && $availableWeight > 0.0;
            // Re-normalise across available components so weights always sum to 1.
            $effectiveWeight = $contributing ? $meta['weight'] / $availableWeight : 0.0;
            if ($contributing) {
                $weighted += $value * $effectiveWeight;
            }
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value === null ? null : (int) round($value),
                'weight' => (int) round($effectiveWeight * 100),
                'contributing' => $contributing,
            ];
        }

        $score = (int) round($weighted);

        return ['score' => $score, 'band' => self::band($score), 'components' => $rows];
    }

    public static function band(int $score): string
    {
        return match (true) {
            $score >= 82 => 'Excellent',
            $score >= 68 => 'Strong',
            $score >= 50 => 'Moderate',
            default => 'Weak',
        };
    }

    /** Clamp a raw signal into the 0-100 range (helper for gatherers). */
    public static function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    /** @param array<string, float|int|null> $components */
    private static function valueOf(array $components, string $key): ?float
    {
        $v = $components[$key] ?? null;

        return $v === null ? null : self::clamp((float) $v);
    }
}
