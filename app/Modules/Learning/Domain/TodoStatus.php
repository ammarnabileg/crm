<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Domain;

/**
 * To-do state, completion mode and priority — all as DATA.
 *
 * Two completion modes (the spec's core distinction):
 *   - `self`    : the assignee can mark it done themselves.
 *   - `manager` : only a supervisor (learning.todo.manage) can move it to done;
 *                 the assignee may progress it but never close it.
 */
final class TodoStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';
    public const BLOCKED = 'blocked';

    /** @var array<string, string> */
    public const STATUSES = [
        self::OPEN => 'Open',
        self::IN_PROGRESS => 'In progress',
        self::DONE => 'Done',
        self::BLOCKED => 'Blocked',
    ];

    public const MODE_SELF = 'self';
    public const MODE_MANAGER = 'manager';

    /** @var array<string, string> */
    public const MODES = [
        self::MODE_SELF => 'Self completion',
        self::MODE_MANAGER => 'Manager controlled',
    ];

    /** @var array<string, string> priority value => label (low→urgent) */
    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::STATUSES);
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function isMode(string $mode): bool
    {
        return array_key_exists($mode, self::MODES);
    }

    public static function isPriority(string $priority): bool
    {
        return array_key_exists($priority, self::PRIORITIES);
    }

    /**
     * Can a user move this to-do to `done`?
     *  - manager mode: only a supervisor (hasManage) may close it.
     *  - self mode:    the assignee or a supervisor may close it.
     */
    public static function canComplete(string $mode, bool $isAssignee, bool $hasManage): bool
    {
        if ($mode === self::MODE_MANAGER) {
            return $hasManage;
        }

        return $isAssignee || $hasManage;
    }
}
