<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\System\HealthController;
use App\Services\System\SystemDiagnostics;
use Tests\TestCase;

/**
 * Production Readiness (Phase 13). The System Status / Health report must cover every
 * Bible category and the app-controlled groups must be green on a healthy install,
 * and the unauthenticated liveness probe must answer. Guards against shipping with a
 * broken storage tree, a missing health category, or a dead /up endpoint.
 */
return new class extends TestCase {
    public function test_diagnostics_cover_every_health_category(): void
    {
        $groups = (new SystemDiagnostics())->run();
        foreach (['Database', 'Storage', 'Cache', 'Queue', 'Mail', 'Scheduler', 'AI Layer'] as $category) {
            $this->assertArrayHasKey($category, $groups, "Health report is missing the {$category} category");
            $this->assertTrue($groups[$category] !== [], "{$category} has no checks");
        }
    }

    public function test_app_controlled_groups_have_no_failures(): void
    {
        // Extensions/Mail/HTTPS depend on the host; Database/Storage/Cache/Queue/
        // Scheduler are within the app's control and must not fail on a healthy box.
        $groups = (new SystemDiagnostics())->run();
        foreach (['Database', 'Storage', 'Cache', 'Queue', 'Scheduler'] as $category) {
            foreach ($groups[$category] as $check) {
                $this->assertTrue(
                    ($check['status'] ?? 'fail') !== 'fail',
                    "{$category} / {$check['name']} failed: " . ($check['value'] ?? '')
                );
            }
        }
    }

    public function test_every_check_has_a_valid_status(): void
    {
        $groups = (new SystemDiagnostics())->run();
        foreach ($groups as $checks) {
            foreach ($checks as $check) {
                $this->assertTrue(in_array($check['status'] ?? '', ['pass', 'warn', 'fail'], true));
            }
        }
    }

    public function test_liveness_probe_returns_ok(): void
    {
        $res = (new HealthController())->up();
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), '"status":"ok"'));
    }
};
