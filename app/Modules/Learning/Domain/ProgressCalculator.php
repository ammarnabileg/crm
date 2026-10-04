<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Domain;

/**
 * Pure, deterministic computation of a learner's progress through a program and
 * whether the program's completion rule is satisfied. No I/O — it consumes plain
 * arrays describing the program's items and the learner's per-item statuses, so
 * it is exhaustively unit-testable.
 */
final class ProgressCalculator
{
    /**
     * @param  list<array{id: string, is_required: bool}>  $items     all items in the program
     * @param  array<string, string>  $statuses  item_id => status (not_started|in_progress|completed)
     * @return array{percent: int, completed: int, total: int, required_total: int, required_done: int}
     */
    public static function compute(array $items, array $statuses): array
    {
        $total = count($items);
        $completed = 0;
        $requiredTotal = 0;
        $requiredDone = 0;

        foreach ($items as $item) {
            $done = ($statuses[$item['id']] ?? 'not_started') === 'completed';
            if ($done) {
                $completed++;
            }
            if ($item['is_required']) {
                $requiredTotal++;
                if ($done) {
                    $requiredDone++;
                }
            }
        }

        $percent = $total > 0 ? (int) round($completed / $total * 100) : 0;

        return [
            'percent' => $percent,
            'completed' => $completed,
            'total' => $total,
            'required_total' => $requiredTotal,
            'required_done' => $requiredDone,
        ];
    }

    /**
     * Is the program complete for this learner, given its completion rule?
     *
     * @param  array{percent: int, completed: int, total: int, required_total: int, required_done: int}  $progress
     */
    public static function isComplete(string $rule, array $progress, int $threshold = 100): bool
    {
        if ($progress['total'] === 0) {
            return false;
        }

        return match ($rule) {
            'all_items' => $progress['completed'] >= $progress['total'],
            'percentage' => $progress['percent'] >= max(1, min(100, $threshold)),
            // default = required_items
            default => $progress['required_total'] === 0
                ? $progress['completed'] >= $progress['total']
                : $progress['required_done'] >= $progress['required_total'],
        };
    }

    /**
     * The enrollment status implied by the progress + rule.
     */
    public static function statusFor(string $rule, array $progress, int $threshold = 100): string
    {
        if (self::isComplete($rule, $progress, $threshold)) {
            return 'completed';
        }

        return $progress['completed'] > 0 ? 'in_progress' : 'not_started';
    }
}
