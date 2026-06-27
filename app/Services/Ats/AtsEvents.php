<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Services\Automation\AutomationEngine;
use Throwable;

/**
 * ATS → Automation bridge (docs/53 "Automation Ready"). Every meaningful ATS change
 * dispatches a domain event (application.submitted, interview.scheduled,
 * offer.accepted, candidate.rejected, job.closed, …) into the Workflow Automation
 * Engine, so tenants can attach automations without code. Dispatch is best-effort:
 * an automation failure never breaks the underlying ATS action.
 */
final class AtsEvents
{
    /** @param array<string,mixed> $context */
    public static function dispatch(string $event, array $context = []): void
    {
        try {
            AutomationEngine::make()->trigger($event, $context);
        } catch (Throwable) {
            // Automations are advisory side-effects; never let one fail an ATS action.
        }
    }
}
