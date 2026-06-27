<?php

declare(strict_types=1);

namespace App\Contracts\Automation;

/**
 * An automation CONDITION (docs/51 Automation Engine). Resolved from the
 * AutomationEngine registry by key so plugins add new conditions without touching
 * core. All of an automation's conditions must evaluate true for its actions to run.
 */
interface AutomationCondition
{
    /** Stable condition key, e.g. 'expression', 'always'. */
    public function key(): string;

    /**
     * @param array<string,mixed> $context the trigger event payload + prior outputs
     * @param array<string,mixed> $config  the step's configuration
     */
    public function evaluate(array $context, array $config): bool;
}
