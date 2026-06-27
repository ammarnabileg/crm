<?php

declare(strict_types=1);

namespace App\Services\Automation\Actions;

use App\Contracts\Automation\AutomationAction;

/** Records a message into the run trace (docs/51). Useful for debugging flows. */
final class LogAction implements AutomationAction
{
    public function key(): string
    {
        return 'log';
    }

    public function run(array $context, array $config): array
    {
        return ['logged' => true, 'message' => (string) ($config['message'] ?? '')];
    }
}
