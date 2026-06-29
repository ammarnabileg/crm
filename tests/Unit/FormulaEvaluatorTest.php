<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Workflow\Domain\Exceptions\FormulaException;
use HaHireAI\Modules\Workflow\Domain\FormulaEvaluator;
use PHPUnit\Framework\TestCase;

/** The sandboxed expression engine behind the Formula (code) node. */
final class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $f;

    protected function setUp(): void
    {
        $this->f = new FormulaEvaluator();
    }

    public function test_arithmetic_and_precedence(): void
    {
        $this->assertSame(7.0, $this->f->evaluate('1 + 2 * 3', []));
        $this->assertSame(9.0, $this->f->evaluate('(1 + 2) * 3', []));
        $this->assertSame(2, $this->f->evaluate('10 % 4', [])); // modulo is integer
    }

    public function test_variables_strings_and_concat(): void
    {
        $this->assertSame('Hello Sara', $this->f->evaluate("'Hello ' + name", ['name' => 'Sara']));
        $this->assertSame('SARA', $this->f->evaluate('upper(name)', ['name' => 'Sara']));
        $this->assertSame('', $this->f->evaluate('missing', []));
    }

    public function test_comparisons_logic_and_ternary(): void
    {
        $this->assertTrue($this->f->evaluate('score >= 80', ['score' => 85]));
        $this->assertFalse($this->f->evaluate('score >= 80', ['score' => 50]));
        $this->assertSame('strong', $this->f->evaluate("score >= 80 ? 'strong' : 'weak'", ['score' => 90]));
        $this->assertTrue($this->f->evaluate('a && b', ['a' => 1, 'b' => 'x']));
        $this->assertFalse($this->f->evaluate('a && b', ['a' => 1, 'b' => '']));
    }

    public function test_whitelisted_functions(): void
    {
        $this->assertSame(3, $this->f->evaluate('length(name)', ['name' => 'abc']));
        $this->assertTrue($this->f->evaluate("contains(email, '@')", ['email' => 'a@b.co']));
        $this->assertSame('a-b', $this->f->evaluate("replace('a_b', '_', '-')", []));
        $this->assertSame(3.0, $this->f->evaluate('round(2.7)', []));
        $this->assertSame('fallback', $this->f->evaluate("coalesce(x, 'fallback')", ['x' => '']));
    }

    public function test_rejects_unknown_function_and_garbage(): void
    {
        $this->expectException(FormulaException::class);
        $this->f->evaluate('system("rm -rf /")', []); // not in the whitelist → rejected
    }

    public function test_cannot_reach_php_or_inject_code(): void
    {
        // The security guarantee: dangerous input NEVER executes — it is either
        // rejected, or resolves to a harmless scalar (e.g. an unknown variable).
        // None of these can reach PHP, the shell, or any object/method.
        foreach (['name->x', '$x', '`ls`', 'eval(name)', 'name; drop', 'phpinfo()'] as $evil) {
            try {
                $result = $this->f->evaluate($evil, ['name' => 'x']);
                $this->assertTrue(is_scalar($result) || $result === null, 'unsafe result for: ' . $evil);
            } catch (FormulaException) {
                $this->addToAssertionCount(1); // rejected outright — also safe
            }
        }
    }
}
