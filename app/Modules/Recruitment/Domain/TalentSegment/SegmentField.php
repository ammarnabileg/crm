<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\TalentSegment;

/**
 * The catalog of criteria a Smart Segment rule can filter on. Each field has a
 * canonical operator and a flag for whether it carries a value (the "available"
 * flag is valueless). This is the single source of truth shared by the rule
 * value object, the SQL compiler and the UI builder — so the three never drift.
 */
final class SegmentField
{
    public const SKILL = 'skill';
    public const LANGUAGE = 'language';
    public const MIN_SCORE = 'min_score';
    public const LAST_INTERVIEW_MONTHS = 'last_interview_months';
    public const AVAILABLE = 'available';
    public const STATUS = 'status';
    public const SENIORITY = 'seniority';

    /**
     * field => [operator, requiresValue, label]. `label` is the human noun used
     * when explaining a match reason and in the builder UI.
     *
     * @var array<string, array{operator: string, requiresValue: bool, label: string}>
     */
    private const CATALOG = [
        self::SKILL => ['operator' => 'like', 'requiresValue' => true, 'label' => 'Skill'],
        self::LANGUAGE => ['operator' => 'like', 'requiresValue' => true, 'label' => 'Language'],
        self::MIN_SCORE => ['operator' => 'gte', 'requiresValue' => true, 'label' => 'Score'],
        self::LAST_INTERVIEW_MONTHS => ['operator' => 'within_months', 'requiresValue' => true, 'label' => 'Last interview'],
        self::AVAILABLE => ['operator' => 'is_true', 'requiresValue' => false, 'label' => 'Available'],
        self::STATUS => ['operator' => 'eq', 'requiresValue' => true, 'label' => 'Status'],
        self::SENIORITY => ['operator' => 'like', 'requiresValue' => true, 'label' => 'Seniority'],
    ];

    public static function isValid(string $field): bool
    {
        return isset(self::CATALOG[$field]);
    }

    public static function requiresValue(string $field): bool
    {
        return self::CATALOG[$field]['requiresValue'] ?? true;
    }

    public static function operator(string $field): string
    {
        return self::CATALOG[$field]['operator'] ?? 'eq';
    }

    public static function label(string $field): string
    {
        return self::CATALOG[$field]['label'] ?? $field;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    /** @return array<string, array{operator: string, requiresValue: bool, label: string}> */
    public static function catalog(): array
    {
        return self::CATALOG;
    }
}
