<?php

declare(strict_types=1);

namespace App\Services\Automation\Actions;

use App\Contracts\Automation\AutomationAction;

/** Ends the automation early (docs/51 "End Workflow"). */
final class EndAction implements AutomationAction
{
    public function key(): string
    {
        return 'end';
    }

    public function run(array $context, array $config): array
    {
        return ['_stop' => true];
    }
}
