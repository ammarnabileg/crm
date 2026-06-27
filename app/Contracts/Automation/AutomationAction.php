<?php

declare(strict_types=1);

namespace App\Contracts\Automation;

/**
 * An automation ACTION (docs/51 Automation Engine). Implementations are resolved
 * from the AutomationEngine registry by key, so plugins add new actions without
 * touching core (Open/Closed). `run()` receives the live run context and the step's
 * config and returns an output array (merged back into the context for later steps).
 * Returning `['_stop' => true]` ends the automation early.
 */
interface AutomationAction
{
    /** Stable action key, e.g. 'send_email', 'webhook', 'run_ai_agent'. */
    public function key(): string;

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     * @return array<string,mixed> output merged into the run context
     */
    public function run(array $context, array $config): array;
}
