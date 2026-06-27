<?php

declare(strict_types=1);

namespace App\Services\Automation\Actions;

use App\Contracts\Automation\AutomationAction;

/**
 * Outbound webhook action (docs/51). Records the intended call into the run trace.
 * The actual HTTP delivery is performed by the queue/integration layer (it needs a
 * tenant-configured endpoint + network), so this action enqueues the payload rather
 * than blocking the automation — keeping the engine deterministic and testable.
 * Config: {url, payload?}.
 */
final class WebhookAction implements AutomationAction
{
    public function key(): string
    {
        return 'webhook';
    }

    public function run(array $context, array $config): array
    {
        return [
            'queued'  => true,
            'url'     => (string) ($config['url'] ?? ''),
            'payload' => $config['payload'] ?? $context,
        ];
    }
}
