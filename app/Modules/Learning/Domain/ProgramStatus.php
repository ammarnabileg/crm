<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Domain;

/**
 * The lifecycle of a learning program, as DATA. A program is authored as a
 * `draft`, made live as `published`, and retired as `archived`. Archived
 * programs keep their enrollments and history (nothing is lost).
 */
final class ProgramStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    /** @var array<string, string> */
    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::PUBLISHED => 'Published',
        self::ARCHIVED => 'Archived',
    ];

    /** @var array<string, string> difficulty value => label */
    public const DIFFICULTIES = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ];

    /** @var array<string, string> completion rule value => label */
    public const COMPLETION_RULES = [
        'all_items' => 'All items complete',
        'required_items' => 'All required items complete',
        'percentage' => 'A percentage of items complete',
    ];

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::STATUSES);
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function isDifficulty(string $value): bool
    {
        return array_key_exists($value, self::DIFFICULTIES);
    }

    public static function isCompletionRule(string $value): bool
    {
        return array_key_exists($value, self::COMPLETION_RULES);
    }
}
