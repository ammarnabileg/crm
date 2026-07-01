<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Learning;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Modules\Learning\Presentation\FeatureGate;
use PHPUnit\Framework\TestCase;

/** Learning is a paid add-on: the gate blocks only when a plan exists without it. */
final class FeatureGateTest extends TestCase
{
    private function resolver(?array $features): EntitlementResolver
    {
        return new class($features) implements EntitlementResolver {
            public function __construct(private readonly ?array $features)
            {
            }

            public function gateFeatures(string $workspaceId): ?array
            {
                return $this->features;
            }

            public function isUsable(string $workspaceId): bool
            {
                return true;
            }

            public function isLocked(string $workspaceId): bool
            {
                return false;
            }
        };
    }

    public function test_allows_when_no_plan_gating(): void
    {
        // null = billing disabled / no plan → do not gate.
        $this->assertNull(FeatureGate::check($this->resolver(null), 'ws'));
    }

    public function test_allows_when_feature_enabled(): void
    {
        $this->assertNull(FeatureGate::check($this->resolver(['jobs', 'learning', 'automation']), 'ws'));
    }

    public function test_blocks_when_feature_absent(): void
    {
        $resp = FeatureGate::check($this->resolver(['jobs', 'interviews']), 'ws');
        $this->assertNotNull($resp);
        $this->assertSame(402, $resp->status());
    }
}
