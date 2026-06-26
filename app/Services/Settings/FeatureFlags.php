<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Feature flags layered on top of per-tenant settings. A feature can be toggled
 * for a workspace without a code change (docs/47 EAS-9); the default comes from
 * config/features.php. Flags are stored under the `feature.<name>` settings key.
 */
final class FeatureFlags
{
    public function __construct(private readonly SettingsManager $settings)
    {
    }

    public function enabled(string $feature): bool
    {
        $default = (bool) config('features.' . $feature, false);

        return (bool) $this->settings->get('feature.' . $feature, $default);
    }

    public function disabled(string $feature): bool
    {
        return ! $this->enabled($feature);
    }

    public function enable(string $feature): void
    {
        $this->settings->set('feature.' . $feature, true);
    }

    public function disable(string $feature): void
    {
        $this->settings->set('feature.' . $feature, false);
    }
}
