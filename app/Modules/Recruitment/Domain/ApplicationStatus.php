<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * The hiring decision workflow as DATA (recruitment spec #9). The AI recommends;
 * a human moves the application through these states — the final decision is
 * always human (docs/AI_ENGINE.md §8.1).
 */
final class ApplicationStatus
{
    /** @var array<string, string> value => label, in pipeline order */
    public const STATUSES = [
        'applied' => 'Applied',
        'ai_screening' => 'AI Screening',
        'qualified' => 'Qualified',
        'disqualified' => 'Disqualified',
        'tech_interview' => 'Tech Interview',
        'manager_interview' => 'Manager Interview',
        'final_review' => 'Final Review',
        'offer' => 'Offer',
        'hired' => 'Hired',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
    ];

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::STATUSES);
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::STATUSES);
    }
}
