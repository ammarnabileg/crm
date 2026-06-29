<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/**
 * The default billing-feature catalog as DATA (docs/WALLET_AND_BILLING.md §3).
 * Seeded into `billing_features`; the platform may edit prices afterwards.
 *
 * Basics (jobs, interviews) are always included at price 0. Premium features are
 * flat monthly charges for enabling *our* platform capability — never a
 * pass-through of a customer's AI/provider spend (Constitution §8): the customer
 * brings their own provider keys.
 *
 * Feature keys MUST match the sidebar feature gates (SidebarBuilder) so enabling a
 * feature in the composed plan reveals its navigation.
 */
final class FeatureCatalog
{
    /**
     * @return list<array{key:string,name:string,description:string,category:string,price_cents:int,sort:int}>
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'jobs', 'name' => 'Jobs', 'description' => 'Create and publish jobs.', 'category' => 'basic', 'price_cents' => 0, 'sort' => 0],
            ['key' => 'interviews', 'name' => 'Interviews', 'description' => 'AI & human interviews.', 'category' => 'basic', 'price_cents' => 0, 'sort' => 1],
            ['key' => 'tasks', 'name' => 'Tasks & Assignment', 'description' => 'Assign work and manage tasks across the team.', 'category' => 'premium', 'price_cents' => 1000, 'sort' => 2],
            ['key' => 'automation', 'name' => 'Workflows & Automation', 'description' => 'No-code recruitment automation (the Workflow product).', 'category' => 'premium', 'price_cents' => 2000, 'sort' => 3],
            ['key' => 'ai', 'name' => 'AI Orchestration', 'description' => 'Route hiring steps through the AI Engine (bring your own provider keys).', 'category' => 'premium', 'price_cents' => 2500, 'sort' => 4],
            ['key' => 'integrations', 'name' => 'Developer & Integrations', 'description' => 'Webhooks and outbound integrations.', 'category' => 'premium', 'price_cents' => 1500, 'sort' => 5],
            ['key' => 'platform_api', 'name' => 'Platform API Access', 'description' => 'Our metered REST API gateway.', 'category' => 'premium', 'price_cents' => 3000, 'sort' => 6],
        ];
    }

    /** Default per-seat monthly price in cents (the one scalar in pricing_catalog). */
    public static function defaultSeatPriceCents(): int
    {
        return 500;
    }
}
