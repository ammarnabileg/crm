<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Learning;

use HaHireAI\Modules\Learning\Domain\QuizGrader;
use PHPUnit\Framework\TestCase;

/** Pure quiz grading — single, multiple and ungradable questions. */
final class QuizGraderTest extends TestCase
{
    private function q(string $id, array $options): array
    {
        return ['id' => $id, 'options' => $options];
    }

    private function opt(string $id, bool $correct): array
    {
        return ['id' => $id, 'is_correct' => $correct];
    }

    public function test_single_choice_scoring(): void
    {
        $questions = [
            $this->q('q1', [$this->opt('a', true), $this->opt('b', false)]),
            $this->q('q2', [$this->opt('c', false), $this->opt('d', true)]),
        ];
        $graded = QuizGrader::grade($questions, ['q1' => ['a'], 'q2' => ['c']]);
        $this->assertSame(1, $graded['score']);
        $this->assertSame(2, $graded['max']);
        $this->assertSame(50, $graded['percent']);
        $this->assertTrue($graded['results']['q1']['correct']);
        $this->assertFalse($graded['results']['q2']['correct']);
    }

    public function test_multiple_choice_requires_exact_set(): void
    {
        $questions = [$this->q('q1', [$this->opt('a', true), $this->opt('b', true), $this->opt('c', false)])];
        $this->assertSame(100, QuizGrader::grade($questions, ['q1' => ['a', 'b']])['percent']);
        $this->assertSame(0, QuizGrader::grade($questions, ['q1' => ['a']])['percent']);       // missing one
        $this->assertSame(0, QuizGrader::grade($questions, ['q1' => ['a', 'b', 'c']])['percent']); // extra wrong
    }

    public function test_ungradable_question_is_skipped(): void
    {
        // No option flagged correct → not counted toward max.
        $questions = [
            $this->q('q1', [$this->opt('a', false), $this->opt('b', false)]),
            $this->q('q2', [$this->opt('c', true)]),
        ];
        $graded = QuizGrader::grade($questions, ['q2' => ['c']]);
        $this->assertSame(1, $graded['max']);
        $this->assertSame(100, $graded['percent']);
    }

    public function test_no_questions_is_zero_percent(): void
    {
        $this->assertSame(0, QuizGrader::grade([], [])['percent']);
    }
}
