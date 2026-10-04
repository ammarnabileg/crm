<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Learning;

use HaHireAI\Modules\Learning\Domain\ProgressCalculator;
use HaHireAI\Modules\Learning\Domain\TodoStatus;
use PHPUnit\Framework\TestCase;

/** Pure progress + completion-rule maths and the to-do completion-mode guard. */
final class ProgressCalculatorTest extends TestCase
{
    private function items(): array
    {
        return [
            ['id' => 'a', 'is_required' => true],
            ['id' => 'b', 'is_required' => true],
            ['id' => 'c', 'is_required' => false],
        ];
    }

    public function test_percent_is_completed_over_total(): void
    {
        $p = ProgressCalculator::compute($this->items(), ['a' => 'completed']);
        $this->assertSame(33, $p['percent']);
        $this->assertSame(1, $p['completed']);
        $this->assertSame(3, $p['total']);
        $this->assertSame(2, $p['required_total']);
        $this->assertSame(1, $p['required_done']);
    }

    public function test_required_items_rule_ignores_optional(): void
    {
        // Both required done, the optional one is not — still complete.
        $p = ProgressCalculator::compute($this->items(), ['a' => 'completed', 'b' => 'completed']);
        $this->assertTrue(ProgressCalculator::isComplete('required_items', $p));
        $this->assertSame('completed', ProgressCalculator::statusFor('required_items', $p));
    }

    public function test_all_items_rule_needs_everything(): void
    {
        $p = ProgressCalculator::compute($this->items(), ['a' => 'completed', 'b' => 'completed']);
        $this->assertFalse(ProgressCalculator::isComplete('all_items', $p));
        $p2 = ProgressCalculator::compute($this->items(), ['a' => 'completed', 'b' => 'completed', 'c' => 'completed']);
        $this->assertTrue(ProgressCalculator::isComplete('all_items', $p2));
    }

    public function test_percentage_rule_respects_threshold(): void
    {
        $p = ProgressCalculator::compute($this->items(), ['a' => 'completed']); // 33%
        $this->assertTrue(ProgressCalculator::isComplete('percentage', $p, 30));
        $this->assertFalse(ProgressCalculator::isComplete('percentage', $p, 50));
    }

    public function test_empty_program_is_never_complete(): void
    {
        $p = ProgressCalculator::compute([], []);
        $this->assertFalse(ProgressCalculator::isComplete('required_items', $p));
        $this->assertSame('not_started', ProgressCalculator::statusFor('required_items', $p));
    }

    public function test_manager_mode_blocks_self_completion(): void
    {
        // Manager mode: assignee cannot complete, manager can.
        $this->assertFalse(TodoStatus::canComplete(TodoStatus::MODE_MANAGER, true, false));
        $this->assertTrue(TodoStatus::canComplete(TodoStatus::MODE_MANAGER, false, true));
        // Self mode: assignee can complete.
        $this->assertTrue(TodoStatus::canComplete(TodoStatus::MODE_SELF, true, false));
        $this->assertFalse(TodoStatus::canComplete(TodoStatus::MODE_SELF, false, false));
    }
}
