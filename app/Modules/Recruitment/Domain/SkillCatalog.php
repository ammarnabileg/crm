<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * The 11 weighted competencies the AI scores per candidate (weights sum to 100).
 * Data, not behaviour — the AI Engine fills the scores; humans decide
 * (docs/AI_ENGINE.md §8.1). Recommendation bands are fixed business policy.
 */
final class SkillCatalog
{
    /** @var array<string, array{label: string, weight: int}> */
    public const SKILLS = [
        'technical_competence' => ['label' => 'Technical competence', 'weight' => 18],
        'communication' => ['label' => 'Communication', 'weight' => 12],
        'problem_solving' => ['label' => 'Problem solving', 'weight' => 12],
        'critical_thinking' => ['label' => 'Critical thinking', 'weight' => 10],
        'self_confidence' => ['label' => 'Self-confidence', 'weight' => 8],
        'leadership' => ['label' => 'Leadership', 'weight' => 8],
        'culture_fit' => ['label' => 'Culture fit', 'weight' => 8],
        'professionalism' => ['label' => 'Professionalism', 'weight' => 8],
        'ai_knowledge' => ['label' => 'AI knowledge', 'weight' => 6],
        'english_proficiency' => ['label' => 'English proficiency', 'weight' => 6],
        'learning_ability' => ['label' => 'Learning ability', 'weight' => 4],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SKILLS);
    }

    /** Map an overall 0–100 score to the fixed recommendation band. */
    public static function band(int $score): string
    {
        return match (true) {
            $score >= 82 => 'strong',       // ✅ توصية قوية
            $score >= 68 => 'suitable',     // ✅ مناسب
            $score >= 50 => 'maybe',        // 🔶 قد يكون مناسباً
            default => 'unsuitable',        // ❌ غير مناسب
        };
    }

    /** Human-facing label for a band. */
    public static function bandLabel(string $band): string
    {
        return match ($band) {
            'strong' => 'Strong recommend',
            'suitable' => 'Suitable',
            'maybe' => 'Maybe suitable',
            default => 'Not suitable',
        };
    }
}
