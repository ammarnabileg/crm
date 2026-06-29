<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health;

use HaHireAI\Core\Contracts\HealthProbe;
use Throwable;

/**
 * Central, pluggable health checker. Probes self-register; results fold into an
 * overall status (a failed `critical` probe ⇒ Unhealthy; a failed `warning`
 * probe ⇒ at least Degraded). See docs/HEALTH_CHECK_SYSTEM.md.
 */
final class HealthChecker
{
    /** @var list<HealthProbe> */
    private array $probes = [];

    public function register(HealthProbe $probe): void
    {
        $this->probes[] = $probe;
    }

    public function count(): int
    {
        return count($this->probes);
    }

    /**
     * Run all probes.
     *
     * @return array{status: HealthStatus, probes: array<string, array{status: string, severity: string, message: string}>}
     */
    public function run(): array
    {
        $overall = HealthStatus::Healthy;
        $report = [];

        foreach ($this->probes as $probe) {
            $result = $this->safeCheck($probe);
            $report[$probe->name()] = [
                'status' => $result->status->value,
                'severity' => $probe->severity(),
                'message' => $result->message,
            ];

            $overall = $this->fold($overall, $result->status, $probe->severity());
        }

        return ['status' => $overall, 'probes' => $report];
    }

    private function safeCheck(HealthProbe $probe): HealthResult
    {
        try {
            return $probe->check();
        } catch (Throwable $e) {
            return HealthResult::unhealthy('Probe threw: ' . $e->getMessage());
        }
    }

    private function fold(HealthStatus $overall, HealthStatus $result, string $severity): HealthStatus
    {
        if ($result === HealthStatus::Healthy) {
            return $overall;
        }

        $contribution = ($severity === 'critical' && $result === HealthStatus::Unhealthy)
            ? HealthStatus::Unhealthy
            : HealthStatus::Degraded;

        return $contribution->rank() > $overall->rank() ? $contribution : $overall;
    }
}
