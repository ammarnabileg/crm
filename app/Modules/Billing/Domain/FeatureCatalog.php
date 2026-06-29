<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/**
 * The default billing-feature catalog as DATA (docs/WALLET_AND_BILLING.md).
 * Seeded into `billing_features`; the platform edits prices via the pricing screen.
 * Basics (jobs, interviews) are always included at price 0. Premium features are
 * flat monthly charges for enabling OUR platform capability, never a pass-through
 * of a customer's AI/provider spend (Constitution section 8) -- the customer
 * brings their own provider keys.
 *
 * Feature keys are the gate keys the SidebarBuilder / module UIs check, so enabling
 * a feature in the composed plan unlocks its module.
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
            ['key' => 'white_label', 'name' => 'White Label & Branding Center', 'description' => 'Full per-company branding across every candidate-facing surface.', 'category' => 'premium', 'price_cents' => 1900, 'sort' => 2],
            ['key' => 'talent_intelligence', 'name' => 'Talent Intelligence', 'description' => 'Hiring KPIs, funnel & pipeline health, recruiter performance and candidate intelligence.', 'category' => 'premium', 'price_cents' => 2900, 'sort' => 3],
            ['key' => 'automation', 'name' => 'Automation Studio', 'description' => 'No-code workflows, approvals, business rules and task automation.', 'category' => 'premium', 'price_cents' => 2000, 'sort' => 4],
            ['key' => 'integrations', 'name' => 'Public API', 'description' => 'API keys, scopes, usage, rate limits, webhooks and playground.', 'category' => 'premium', 'price_cents' => 500, 'sort' => 5],
            ['key' => 'talent_crm', 'name' => 'Talent CRM', 'description' => 'Pools, segments, campaigns, follow-ups and communication history.', 'category' => 'premium', 'price_cents' => 2900, 'sort' => 6],
            ['key' => 'communication', 'name' => 'Communication Center', 'description' => 'Bring-your-own email providers, templates, tracking and signatures.', 'category' => 'premium', 'price_cents' => 1500, 'sort' => 7],
            ['key' => 'audit_logs', 'name' => 'Audit Logs', 'description' => 'Full activity timeline with diff viewer, filters and export.', 'category' => 'premium', 'price_cents' => 1000, 'sort' => 8],
            ['key' => 'ai', 'name' => 'AI Orchestration', 'description' => 'Route hiring steps through the AI Engine (bring your own provider keys).', 'category' => 'premium', 'price_cents' => 2500, 'sort' => 9],
            ['key' => 'ai_avatar', 'name' => 'AI Avatar Interviews', 'description' => 'Avatar-led interviews via your own HeyGen key.', 'category' => 'premium', 'price_cents' => 3900, 'sort' => 10],
            ['key' => 'ai_voice', 'name' => 'AI Voice Interviews', 'description' => 'Voice interviews via your own speech provider (Azure/ElevenLabs/Deepgram/Google/OpenAI).', 'category' => 'premium', 'price_cents' => 3900, 'sort' => 11],
            ['key' => 'career_domain', 'name' => 'Custom Career Domain', 'description' => 'Connect careers.yourcompany.com to your career page.', 'category' => 'premium', 'price_cents' => 1500, 'sort' => 12],
            ['key' => 'support_center', 'name' => 'Support Center', 'description' => 'Tickets, SLA, knowledge base, announcements and changelog.', 'category' => 'premium', 'price_cents' => 1900, 'sort' => 13],
            ['key' => 'tasks', 'name' => 'Tasks & Assignment', 'description' => 'Assign work and manage tasks across the team.', 'category' => 'premium', 'price_cents' => 1000, 'sort' => 14],
        ];
    }

    /** Default per-seat monthly price in cents ($9/seat; the Owner seat is free). */
    public static function defaultSeatPriceCents(): int
    {
        return 900;
    }
}
