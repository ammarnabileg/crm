<?php

declare(strict_types=1);

namespace App\Services\Automation\Conditions;

use App\Contracts\Automation\AutomationCondition;

/**
 * Generic expression condition (docs/51 Automation Engine) — e.g. "Score > 80",
 * "Experience >= 5", "Language = Arabic". Config: {field, op, value}. Evaluated
 * against the trigger context + prior step outputs.
 */
final class ExpressionCondition implements AutomationCondition
{
    public function key(): string
    {
        return 'expression';
    }

    public function evaluate(array $context, array $config): bool
    {
        $field = (string) ($config['field'] ?? '');
        $op = (string) ($config['op'] ?? '==');
        $expected = $config['value'] ?? null;
        $actual = $context[$field] ?? null;

        return match ($op) {
            '==', 'eq'  => $actual == $expected,
            '!=', 'neq' => $actual != $expected,
            '<', 'lt'   => is_numeric($actual) && (float) $actual < (float) $expected,
            '<=', 'lte' => is_numeric($actual) && (float) $actual <= (float) $expected,
            '>', 'gt'   => is_numeric($actual) && (float) $actual > (float) $expected,
            '>=', 'gte' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'in'        => is_array($expected) && in_array($actual, $expected, false),
            'not_in'    => is_array($expected) && ! in_array($actual, $expected, false),
            'contains'  => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            default      => false,
        };
    }
}
