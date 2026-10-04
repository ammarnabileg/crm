<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/**
 * The default plan catalog as DATA (docs/BILLING_PLATFORM.md §2). Plans are
 * seeded into the `plans` table; nothing in code branches on a plan code. A
 * limit of -1 means unlimited.
 */
final class PlanCatalog
{
    /**
     * @return list<array{
     *   code: string, name: string, description: string, price_cents: int,
     *   currency: string, interval: string, trial_days: int,
     *   features: list<string>, limits: array<string,int>, sort: int
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'Get started with the essentials.',
                'price_cents' => 0,
                'currency' => 'USD',
                'interval' => 'month',
                'trial_days' => 0,
                'features' => [],
                'limits' => ['members' => 3, 'jobs' => 3, 'api_tokens' => 1, 'workflows' => 1, 'webhooks' => 1, 'ai_runs_month' => 50],
                'sort' => 0,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'AI, automation and integrations for growing teams.',
                'price_cents' => 4900,
                'currency' => 'USD',
                'interval' => 'month',
                'trial_days' => 14,
                'features' => ['ai', 'automation', 'integrations'],
                'limits' => ['members' => 25, 'jobs' => 50, 'api_tokens' => 10, 'workflows' => 25, 'webhooks' => 10, 'ai_runs_month' => 5000],
                'sort' => 1,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Unlimited scale, SSO and priority support.',
                'price_cents' => 19900,
                'currency' => 'USD',
                'interval' => 'month',
                'trial_days' => 14,
                'features' => ['ai', 'automation', 'integrations', 'sso', 'priority_support'],
                'limits' => ['members' => -1, 'jobs' => -1, 'api_tokens' => -1, 'workflows' => -1, 'webhooks' => -1, 'ai_runs_month' => -1],
                'sort' => 2,
            ],
        ];
    }
}
