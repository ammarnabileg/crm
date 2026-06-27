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
}
