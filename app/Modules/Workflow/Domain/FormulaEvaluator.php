<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Domain;

use HaHireAI\Modules\Workflow\Domain\Exceptions\FormulaException;

/**
 * A SANDBOXED expression evaluator — the safe engine behind the "Formula" (code)
 * node. It gives users code-like power (math, text, comparisons, conditionals, a
 * fixed set of pure functions) over their workflow variables, while being
 * incapable of touching the filesystem, network, database, PHP/JS runtime, other
 * workspaces, or the platform. There is NO eval(), NO arbitrary code, NO loops,
 * NO property/object access, NO I/O — only this fixed grammar over scalars.
 *
 * This is deliberately NOT raw JavaScript: running tenant-supplied code on a
 * shared multi-tenant server is an RCE / cross-tenant risk. A bounded expression
 * sandbox delivers the requested power without that risk (PROJECT_CONSTITUTION §10).
 *
 * Grammar (precedence climbing):
 *   ternary  ?:            (lowest)
 *   || && | == != | < <= > >= | + - | * / %   | unary - !  | primary (highest)
 *   primary = number | 'string' | true|false|null | ident | func(args) | ( expr )
 */
final class FormulaEvaluator
{
    private const MAX_LENGTH = 1000;
    private const MAX_DEPTH = 40;

    /** @var list<array{t:string,v:mixed}> */
    private array $tokens = [];
    private int $pos = 0;
    private int $depth = 0;

    /** Whitelisted pure functions. Each gets already-evaluated scalar args. */
    private const FUNCS = [
        'upper', 'lower', 'trim', 'length', 'round', 'floor', 'ceil', 'abs',
        'contains', 'replace', 'substr', 'concat', 'coalesce', 'ifEmpty',
        'number', 'min', 'max', 'now', 'date',
    ];

    /**
     * Evaluate an expression against a flat map of variables. Returns a scalar.
     *
     * @param  array<string, mixed>  $vars
     */
    public function evaluate(string $expression, array $vars): mixed
    {
        if (strlen($expression) > self::MAX_LENGTH) {
            throw new FormulaException('Formula is too long.');
        }

        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $this->depth = 0;

        if ($this->tokens === []) {
            return '';
        }

        $value = $this->parseTernary($vars);
        if ($this->pos < count($this->tokens)) {
            throw new FormulaException('Unexpected “' . ($this->tokens[$this->pos]['v'] ?? '') . '” in formula.');
        }

        return $value;
    }

    /** @return list<array{t:string,v:mixed}> */
    private function tokenize(string $s): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($s);
        $ops = ['==', '!=', '<=', '>=', '&&', '||'];

        while ($i < $len) {
            $c = $s[$i];

            if (ctype_space($c)) { $i++; continue; }

            // String literal: '...' or "..."
            if ($c === "'" || $c === '"') {
                $quote = $c; $i++; $buf = '';
                while ($i < $len && $s[$i] !== $quote) {
                    if ($s[$i] === '\\' && $i + 1 < $len) { $buf .= $s[$i + 1]; $i += 2; continue; }
                    $buf .= $s[$i]; $i++;
                }
                if ($i >= $len) { throw new FormulaException('Unterminated string in formula.'); }
                $i++; $tokens[] = ['t' => 'str', 'v' => $buf];
                continue;
            }

            // Number
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                $buf = '';
                while ($i < $len && (ctype_digit($s[$i]) || $s[$i] === '.')) { $buf .= $s[$i]; $i++; }
                $tokens[] = ['t' => 'num', 'v' => $buf + 0];
                continue;
            }

            // Identifier / function / keyword
            if (ctype_alpha($c) || $c === '_') {
                $buf = '';
                while ($i < $len && (ctype_alnum($s[$i]) || $s[$i] === '_' || $s[$i] === '.')) { $buf .= $s[$i]; $i++; }
                $low = strtolower($buf);
                if ($low === 'true' || $low === 'false') { $tokens[] = ['t' => 'bool', 'v' => $low === 'true']; }
                elseif ($low === 'null') { $tokens[] = ['t' => 'null', 'v' => null]; }
                else { $tokens[] = ['t' => 'ident', 'v' => $buf]; }
                continue;
            }

            // Two-char operators
            if ($i + 1 < $len && in_array($s[$i] . $s[$i + 1], $ops, true)) {
                $tokens[] = ['t' => 'op', 'v' => $s[$i] . $s[$i + 1]]; $i += 2; continue;
            }

            // Single-char operators / punctuation
            if (strpos('+-*/%()<>!?:,', $c) !== false) {
                $tokens[] = ['t' => 'op', 'v' => $c]; $i++; continue;
            }

            throw new FormulaException('Unexpected character “' . $c . '” in formula.');
        }

        return $tokens;
    }

    private function peek(): ?array { return $this->tokens[$this->pos] ?? null; }
    private function next(): ?array { return $this->tokens[$this->pos++] ?? null; }

    private function eatOp(string $op): bool
    {
        $t = $this->peek();
        if ($t !== null && $t['t'] === 'op' && $t['v'] === $op) { $this->pos++; return true; }

        return false;
    }

    /** @param array<string,mixed> $vars */
    private function parseTernary(array $vars): mixed
    {
        $this->guardDepth();
        $cond = $this->parseBinary($vars, 0);
        if ($this->eatOp('?')) {
            $then = $this->parseTernary($vars);
            if (! $this->eatOp(':')) { throw new FormulaException('Expected “:” in formula.'); }
            $else = $this->parseTernary($vars);
            $this->depth--;

            return $this->truthy($cond) ? $then : $else;
        }
        $this->depth--;

        return $cond;
    }

    /** Precedence table for binary operators. */
    private const PREC = ['||' => 1, '&&' => 2, '==' => 3, '!=' => 3, '<' => 4, '<=' => 4, '>' => 4, '>=' => 4, '+' => 5, '-' => 5, '*' => 6, '/' => 6, '%' => 6];

    /** @param array<string,mixed> $vars */
    private function parseBinary(array $vars, int $minPrec): mixed
    {
        $left = $this->parseUnary($vars);

        while (true) {
            $t = $this->peek();
            if ($t === null || $t['t'] !== 'op' || ! isset(self::PREC[$t['v']]) || self::PREC[$t['v']] < $minPrec) {
                break;
            }
            $op = (string) $this->next()['v'];
            $right = $this->parseBinary($vars, self::PREC[$op] + 1);
            $left = $this->apply($op, $left, $right);
        }

        return $left;
    }

    /** @param array<string,mixed> $vars */
    private function parseUnary(array $vars): mixed
    {
        if ($this->eatOp('-')) { return -1 * $this->toNumber($this->parseUnary($vars)); }
        if ($this->eatOp('!')) { return ! $this->truthy($this->parseUnary($vars)); }

        return $this->parsePrimary($vars);
    }

    /** @param array<string,mixed> $vars */
    private function parsePrimary(array $vars): mixed
    {
        $t = $this->next();
        if ($t === null) { throw new FormulaException('Unexpected end of formula.'); }

        if ($t['t'] === 'num' || $t['t'] === 'str' || $t['t'] === 'bool' || $t['t'] === 'null') {
            return $t['v'];
        }

        if ($t['t'] === 'op' && $t['v'] === '(') {
            $this->guardDepth();
            $v = $this->parseTernary($vars);
            if (! $this->eatOp(')')) { throw new FormulaException('Expected “)” in formula.'); }
            $this->depth--;

            return $v;
        }

        if ($t['t'] === 'ident') {
            $name = (string) $t['v'];
            // Function call?
            if ($this->peek() !== null && $this->peek()['t'] === 'op' && $this->peek()['v'] === '(') {
                $this->pos++;
                $args = [];
                if (! ($this->peek() !== null && $this->peek()['t'] === 'op' && $this->peek()['v'] === ')')) {
                    do { $args[] = $this->parseTernary($vars); } while ($this->eatOp(','));
                }
                if (! $this->eatOp(')')) { throw new FormulaException('Expected “)” after arguments.'); }

                return $this->callFunc(strtolower($name), $args);
            }

            // Variable reference (supports dotted names as flat keys).
            return $vars[$name] ?? '';
        }

        throw new FormulaException('Unexpected “' . (string) $t['v'] . '” in formula.');
    }

    private function apply(string $op, mixed $a, mixed $b): mixed
    {
        return match ($op) {
            '+' => is_numeric($a) && is_numeric($b) ? $a + $b : $this->str($a) . $this->str($b),
            '-' => $this->toNumber($a) - $this->toNumber($b),
            '*' => $this->toNumber($a) * $this->toNumber($b),
            '/' => $this->toNumber($b) == 0.0 ? 0 : $this->toNumber($a) / $this->toNumber($b),
            '%' => (int) $this->toNumber($b) === 0 ? 0 : (int) $this->toNumber($a) % (int) $this->toNumber($b),
            '==' => $this->looseEq($a, $b),
            '!=' => ! $this->looseEq($a, $b),
            '<' => $this->toNumber($a) < $this->toNumber($b),
            '<=' => $this->toNumber($a) <= $this->toNumber($b),
            '>' => $this->toNumber($a) > $this->toNumber($b),
            '>=' => $this->toNumber($a) >= $this->toNumber($b),
            '&&' => $this->truthy($a) && $this->truthy($b),
            '||' => $this->truthy($a) ? $a : $b,
            default => throw new FormulaException('Unknown operator ' . $op),
        };
    }

    /** @param list<mixed> $a */
    private function callFunc(string $fn, array $a): mixed
    {
        if (! in_array($fn, self::FUNCS, true)) {
            throw new FormulaException('Unknown function “' . $fn . '”.');
        }

        return match ($fn) {
            'upper' => mb_strtoupper($this->str($a[0] ?? '')),
            'lower' => mb_strtolower($this->str($a[0] ?? '')),
            'trim' => trim($this->str($a[0] ?? '')),
            'length' => mb_strlen($this->str($a[0] ?? '')),
            'round' => round($this->toNumber($a[0] ?? 0), (int) ($a[1] ?? 0)),
            'floor' => floor($this->toNumber($a[0] ?? 0)),
            'ceil' => ceil($this->toNumber($a[0] ?? 0)),
            'abs' => abs($this->toNumber($a[0] ?? 0)),
            'min' => min($this->toNumber($a[0] ?? 0), $this->toNumber($a[1] ?? 0)),
            'max' => max($this->toNumber($a[0] ?? 0), $this->toNumber($a[1] ?? 0)),
            'number' => $this->toNumber($a[0] ?? 0),
            'contains' => str_contains($this->str($a[0] ?? ''), $this->str($a[1] ?? '')),
            'replace' => str_replace($this->str($a[1] ?? ''), $this->str($a[2] ?? ''), $this->str($a[0] ?? '')),
            'substr' => mb_substr($this->str($a[0] ?? ''), (int) $this->toNumber($a[1] ?? 0), isset($a[2]) ? (int) $this->toNumber($a[2]) : null),
            'concat' => implode('', array_map([$this, 'str'], $a)),
            'coalesce', 'ifEmpty' => $this->firstNonEmpty($a),
            'now' => gmdate('Y-m-d H:i:s'),
            'date' => gmdate($this->str($a[0] ?? 'Y-m-d')),
            default => '',
        };
    }

    /** @param list<mixed> $a */
    private function firstNonEmpty(array $a): mixed
    {
        foreach ($a as $v) {
            if ($v !== null && $v !== '' && $v !== false) { return $v; }
        }

        return '';
    }

    private function looseEq(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) { return $a == $b; }

        return $this->str($a) === $this->str($b);
    }

    private function truthy(mixed $v): bool
    {
        if (is_bool($v)) { return $v; }
        if (is_numeric($v)) { return (float) $v !== 0.0; }

        return $v !== null && $v !== '';
    }

    private function toNumber(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function str(mixed $v): string
    {
        if (is_bool($v)) { return $v ? 'true' : 'false'; }
        if ($v === null) { return ''; }

        return (string) $v;
    }

    private function guardDepth(): void
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw new FormulaException('Formula is nested too deeply.');
        }
    }
}
