<?php

declare(strict_types=1);

namespace App\Services\Automation\Conditions;

use App\Contracts\Automation\AutomationCondition;

/** An always-true condition (docs/51) — for unconditional automations. */
final class AlwaysCondition implements AutomationCondition
{
    public function key(): string
    {
        return 'always';
    }

    public function evaluate(array $context, array $config): bool
    {
        return true;
    }
}
