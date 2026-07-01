<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use PHPUnit\Framework\TestCase;

/** The single sidebar is a pure function of context + permissions (no roles). */
final class SidebarBuilderTest extends TestCase
{
    public function test_items_are_gated_by_permission(): void
    {
        $sidebar = new SidebarBuilder();

        $labels = $sidebar->labels('workspace', ['workspace.view', 'job.view']);

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Jobs', $labels);
        $this->assertNotContains('Settings', $labels);
        $this->assertNotContains('Billing', $labels);
    }

    public function test_owner_and_recruiter_sidebars_differ(): void
    {
        $sidebar = new SidebarBuilder();

        $owner = $sidebar->labels('workspace', [
            'workspace.view', 'job.view', 'candidate.view', 'member.view',
            'role.view', 'settings.view', 'billing.view', 'audit.view',
        ]);
        $recruiter = $sidebar->labels('workspace', ['job.view', 'candidate.view']);

        $this->assertContains('Settings', $owner);
        $this->assertContains('Billing', $owner);
        $this->assertContains('Members', $owner);
        $this->assertSame(['Jobs', 'Candidates'], $recruiter);
        $this->assertNotSame($owner, $recruiter);
    }

    public function test_platform_context_uses_system_permissions(): void
    {
        $sidebar = new SidebarBuilder();

        $labels = $sidebar->labels('platform', ['system.dashboard.view', 'system.workspaces.manage']);

        $this->assertContains('Overview', $labels);
        $this->assertContains('Workspaces', $labels);
        $this->assertNotContains('Jobs', $labels);
    }

    public function test_no_permissions_yields_empty_sidebar(): void
    {
        $this->assertSame([], (new SidebarBuilder())->build('workspace', []));
    }

    public function test_candidate_context_is_not_permission_gated(): void
    {
        // A candidate holds no role/permissions — the menu is context-driven.
        $labels = (new SidebarBuilder())->labels('candidate', []);

        $this->assertSame(['My Applications', 'Available Jobs', 'My Insights', 'My Profile'], $labels);
    }

    public function test_workspace_split_ai_and_human_interviews(): void
    {
        $labels = (new SidebarBuilder())->labels('workspace', ['workspace.view', 'interview.view']);

        $this->assertContains('AI Interviews', $labels);
        $this->assertContains('Human Interviews', $labels);
    }

    public function test_same_user_sees_platform_or_workspace_sidebar_by_context(): void
    {
        // One identity, two contexts — the SAME builder, no per-role sidebars.
        $sidebar = new SidebarBuilder();
        $workspaceKeys = ['workspace.view', 'job.view', 'candidate.view'];
        $systemKeys = ['system.dashboard.view', 'system.workspaces.manage', 'system.users.manage'];

        $inWorkspace = $sidebar->labels('workspace', $workspaceKeys);
        $asSystemOwner = $sidebar->labels('platform', $systemKeys);

        $this->assertContains('Jobs', $inWorkspace);
        $this->assertNotContains('Jobs', $asSystemOwner);
        $this->assertContains('Workspaces', $asSystemOwner); // "Companies"
        $this->assertContains('Users', $asSystemOwner);
    }

    public function test_every_built_item_carries_a_group_heading(): void
    {
        $sidebar = new SidebarBuilder();

        // An all-permissions staff view and the platform view both expose a
        // non-empty `group` on every item; groups drive the section headings.
        $workspace = $sidebar->build('workspace', [
            'workspace.view', 'job.view', 'candidate.view', 'pipeline.view', 'talent.view',
            'interview.view', 'offer.view', 'avatar.view', 'report.view', 'learning.view',
            'workflow.view', 'integration.view', 'member.view', 'role.view', 'audit.view',
            'task.view', 'search.use', 'files.view', 'ai.view', 'workspace.branding',
            'settings.view', 'settings.update', 'billing.view',
        ]);
        $this->assertNotSame([], $workspace);
        foreach ($workspace as $item) {
            $this->assertArrayHasKey('group', $item);
            $this->assertNotSame('', $item['group']);
        }

        $platform = $sidebar->build('platform', [
            'system.dashboard.view', 'system.workspaces.manage', 'system.users.manage',
            'system.roles.manage', 'system.subscriptions.manage', 'system.pricing.manage',
            'system.ai.manage', 'system.audit.view', 'system.diagnostics.run', 'system.settings.manage',
        ]);
        foreach ($platform as $item) {
            $this->assertNotSame('', $item['group']);
        }

        // Candidate items are context-driven but still grouped under "My Space".
        foreach ($sidebar->build('candidate', []) as $item) {
            $this->assertSame('My Space', $item['group']);
        }
    }

    public function test_recruiter_only_sees_recruiting_and_insights_groups(): void
    {
        // A recruiter with the four view perms below sees exactly two groups:
        // Recruiting (Jobs/Candidates/AI Interviews) and Insights (Reports/First
        // Impression). No Overview, no Settings, no Team — those items are gated
        // out, so their headings never render.
        $items = (new SidebarBuilder())->build('workspace', [
            'job.view', 'candidate.view', 'interview.view', 'report.view',
        ]);

        $groups = array_values(array_unique(array_map(
            static fn (array $i): string => $i['group'],
            $items,
        )));

        $this->assertSame(['Recruiting', 'Insights'], $groups);
    }

    public function test_learning_group_disappears_without_the_learning_feature(): void
    {
        $sidebar = new SidebarBuilder();
        $keys = ['workspace.view', 'learning.view'];

        // Plan without the learning feature → no Learning items, no heading.
        $free = $sidebar->build('workspace', $keys, []);
        $freeGroups = array_map(static fn (array $i): string => $i['group'], $free);
        $this->assertNotContains('Learning', $freeGroups);

        // Enable the feature → the Learning group returns.
        $pro = $sidebar->build('workspace', $keys, ['learning']);
        $proGroups = array_map(static fn (array $i): string => $i['group'], $pro);
        $this->assertContains('Learning', $proGroups);
    }

    public function test_feature_gated_items_hide_when_the_plan_lacks_the_feature(): void
    {
        $sidebar = new SidebarBuilder();
        $keys = ['workspace.view', 'job.view', 'ai.view', 'workflow.view', 'integration.view'];

        // Free plan (no premium features) → AI / Workflows / Developer disappear…
        $free = $sidebar->labels('workspace', $keys, []);
        $this->assertContains('Jobs', $free);
        $this->assertNotContains('AI', $free);
        $this->assertNotContains('Workflows', $free);
        $this->assertNotContains('Developer', $free);

        // …but the permission alone still shows them once the plan enables them.
        $pro = $sidebar->labels('workspace', $keys, ['ai', 'automation', 'integrations']);
        $this->assertContains('AI', $pro);
        $this->assertContains('Workflows', $pro);

        // No subscription (null) → do not gate (back-compatible behaviour).
        $this->assertContains('AI', $sidebar->labels('workspace', $keys, null));
    }
}
