<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Domain;

/**
 * The kinds of content an author can drop into a section. Each section holds any
 * number of items in any mix. `quiz` is architecturally supported (tables +
 * type) though its authoring/taking UI is intentionally minimal for now.
 */
final class ItemType
{
    public const LESSON = 'lesson';
    public const VIDEO = 'video';
    public const DOCUMENT = 'document';
    public const LINK = 'link';
    public const TASK = 'task';
    public const TODO_LIST = 'todo_list';
    public const QUIZ = 'quiz';
    public const NOTE = 'note';

    /** @var array<string, array{label: string, icon: string, hint: string}> */
    public const TYPES = [
        self::LESSON => ['label' => 'Lesson', 'icon' => 'book-open', 'hint' => 'Rich-text learning content'],
        self::VIDEO => ['label' => 'Video', 'icon' => 'play-circle', 'hint' => 'An embedded or linked video'],
        self::DOCUMENT => ['label' => 'Document', 'icon' => 'file-text', 'hint' => 'An uploaded file (PDF, slides…)'],
        self::LINK => ['label' => 'External link', 'icon' => 'link', 'hint' => 'A link to an external resource'],
        self::TASK => ['label' => 'Task', 'icon' => 'check-square', 'hint' => 'A single actionable task'],
        self::TODO_LIST => ['label' => 'To-do list', 'icon' => 'list-checks', 'hint' => 'A checklist of to-dos'],
        self::QUIZ => ['label' => 'Quiz', 'icon' => 'help-circle', 'hint' => 'Knowledge check (beta)'],
        self::NOTE => ['label' => 'Note', 'icon' => 'sticky-note', 'hint' => 'A short note or callout'],
    ];

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst($type);
    }

    public static function icon(string $type): string
    {
        return self::TYPES[$type]['icon'] ?? 'square';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::TYPES);
    }
}
