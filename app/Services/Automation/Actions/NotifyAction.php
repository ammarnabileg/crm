<?php

declare(strict_types=1);

namespace App\Services\Automation\Actions;

use App\Contracts\Automation\AutomationAction;

/**
 * Notification action (docs/51): Email / SMS / WhatsApp / Push / Slack / Teams. It
 * enqueues a notification intent into the run trace; actual delivery is handled by
 * the platform notification system (channel adapters), so the engine stays
 * deterministic and channel-agnostic. Config: {channel, to, template?, message?}.
 */
final class NotifyAction implements AutomationAction
{
    public function key(): string
    {
        return 'notify';
    }

    public function run(array $context, array $config): array
    {
        return [
            'queued'   => true,
            'channel'  => (string) ($config['channel'] ?? 'in_app'),
            'to'       => $config['to'] ?? null,
            'template' => $config['template'] ?? null,
            'message'  => (string) ($config['message'] ?? ''),
        ];
    }
}
