<?php

declare(strict_types=1);

/**
 * Workflow Automation Engine catalog (docs/51). Triggers, conditions and actions
 * are config + registry driven, so plugins extend them without code changes. The
 * `triggers` list is the set of domain events automations may bind to; `conditions`
 * and `actions` list the built-in keys (plugins register more at runtime).
 */
return [
    'max_steps' => 100,

    'triggers' => [
        'user.registered', 'workspace.created', 'subscription.activated', 'subscription.expired',
        'job.published', 'job.closed', 'application.submitted', 'application.reviewed',
        'interview.scheduled', 'interview.started', 'interview.completed',
        'candidate.passed', 'candidate.rejected', 'offer.created', 'offer.accepted',
        'offer.declined', 'invoice.paid', 'invoice.failed', 'ai.interview.finished',
        'ai.cost_limit.reached', 'plugin.installed', 'system.error', 'webhook.received',
        'custom.event',
    ],

    // Built-in step keys (plugins add more via AutomationEngine::register*).
    'conditions' => ['expression', 'always'],
    'actions'    => ['log', 'set_context', 'webhook', 'notify', 'end'],
];
