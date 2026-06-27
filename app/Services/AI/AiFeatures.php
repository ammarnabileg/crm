<?php

declare(strict_types=1);

namespace App\Services\AI;

use Throwable;

/**
 * Resolver for AI capability flags (docs/51 §20). Decides whether an AI subsystem
 * (memory engine, cheating detection, voice/video analysis, multi-agent, explainable
 * AI, knowledge engine, prompt optimizer, workflow builder) is enabled — WITHOUT any
 * code change to turn one on or off.
 *
 * Resolution order for a flag `<key>`:
 *   1. A PER-TENANT override, if the existing platform feature-flag service
 *      (App\Services\Settings\FeatureFlags, bound as 'features') has one stored under
 *      `ai_feature.<key>`. This service is consulted READ-ONLY — we never write to it
 *      and never mutate tenant settings here. Its lookup needs an active tenant, so
 *      the call is wrapped defensively: if it cannot be resolved safely (no tenant,
 *      no binding, any error) we silently fall back to config.
 *   2. The PLATFORM default in config/ai_features.php (`ai_features.flags.<key>`).
 *   3. The caller-supplied $default (defaults to false for an unknown key).
 *
 * HOW TO TOGGLE A FEATURE (no code change either way):
 *   - Platform-wide: edit the boolean in config/ai_features.php.
 *   - Per tenant:    set the `ai_feature.<key>` flag via the feature-flag system,
 *                    e.g. app('features')->enable('ai_feature.voice_analysis').
 */
final class AiFeatures
{
    /** Settings/feature-flag key prefix under which per-tenant overrides live. */
    private const OVERRIDE_PREFIX = 'ai_feature.';

    /**
     * Whether an AI capability is enabled. A per-tenant override (read from the
     * existing feature-flag service) wins; otherwise the config default applies.
     */
    public function enabled(string $key, ?bool $default = null): bool
    {
        $configDefault = config('ai_features.flags.' . $key, $default ?? false);

        $override = $this->tenantOverride($key);
        if ($override !== null) {
            return $override;
        }

        return (bool) $configDefault;
    }

    /**
     * The fully resolved flag map (config defaults overlaid with any per-tenant
     * overrides), keyed by flag name.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $flags = config('ai_features.flags', []);
        $resolved = [];

        foreach ((array) $flags as $key => $_default) {
            $resolved[$key] = $this->enabled((string) $key);
        }

        return $resolved;
    }

    /**
     * Read a per-tenant override from the existing feature-flag service, READ-ONLY.
     * Returns the boolean override if one is STORED for this tenant, or null if there
     * is no stored override (or the service cannot be consulted safely).
     */
    private function tenantOverride(string $key): ?bool
    {
        $settingKey = self::OVERRIDE_PREFIX . $key;

        try {
            $settings = app('settings');
            // A sentinel distinguishes "no stored override" from a stored false.
            $stored = $settings->get('feature.' . $settingKey, '__unset__');
            if ($stored === '__unset__') {
                return null;
            }

            return (bool) $stored;
        } catch (Throwable) {
            // No active tenant / binding / any failure → defer to config.
            return null;
        }
    }
}
