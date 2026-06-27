<?php

declare(strict_types=1);

namespace App\Services\Automation\Actions;

use App\Contracts\Automation\AutomationAction;

/**
 * Merges fixed values into the run context (docs/51) so later conditions/actions can
 * branch on them. Config: {values: {...}}.
 */
final class SetContextAction implements AutomationAction
{
    public function key(): string
    {
        return 'set_context';
    }

    public function run(array $context, array $config): array
    {
        $values = $config['values'] ?? [];

        return is_array($values) ? $values : [];
    }
}
