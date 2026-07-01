<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Contracts\HealthProbe;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Health\HealthResult;
use PHPUnit\Framework\TestCase;

final class HealthCheckerTest extends TestCase
{
    public function test_all_healthy_is_healthy(): void
    {
        $checker = new HealthChecker();
        $checker->register($this->probe('a', 'critical', HealthResult::ok()));
        $checker->register($this->probe('b', 'warning', HealthResult::ok()));

        $this->assertSame('healthy', $checker->run()['status']->value);
    }

    public function test_failed_warning_is_degraded(): void
    {
        $checker = new HealthChecker();
        $checker->register($this->probe('a', 'critical', HealthResult::ok()));
        $checker->register($this->probe('b', 'warning', HealthResult::unhealthy('down')));

        $this->assertSame('degraded', $checker->run()['status']->value);
    }

    public function test_failed_critical_is_unhealthy(): void
    {
        $checker = new HealthChecker();
        $checker->register($this->probe('a', 'critical', HealthResult::unhealthy('down')));

        $this->assertSame('unhealthy', $checker->run()['status']->value);
    }

    public function test_probe_exception_is_caught_as_unhealthy(): void
    {
        $checker = new HealthChecker();
        $checker->register(new class implements HealthProbe {
            public function name(): string
            {
                return 'boom';
            }

            public function severity(): string
            {
                return 'critical';
            }

            public function check(): HealthResult
            {
                throw new \RuntimeException('kaboom');
            }
        });

        $report = $checker->run();
        $this->assertSame('unhealthy', $report['status']->value);
        $this->assertSame('unhealthy', $report['probes']['boom']['status']);
    }

    private function probe(string $name, string $severity, HealthResult $result): HealthProbe
    {
        return new class ($name, $severity, $result) implements HealthProbe {
            public function __construct(
                private string $name,
                private string $severity,
                private HealthResult $result,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function severity(): string
            {
                return $this->severity;
            }

            public function check(): HealthResult
            {
                return $this->result;
            }
        };
    }
}
