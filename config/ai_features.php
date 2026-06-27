<?php

declare(strict_types=1);

/**
 * AI capability feature flags (docs/51 §20). Each AI subsystem of the Interview
 * Engine can be turned on or off WITHOUT a code change. The value here is the
 * platform default; a tenant may override any flag at runtime through the existing
 * per-tenant feature-flag system (App\Services\Settings\FeatureFlags, stored under
 * the settings key `ai_feature.<key>`) — see App\Services\AI\AiFeatures for how the
 * override is resolved.
 *
 * To toggle a capability:
 *   - PLATFORM-WIDE default: flip the boolean below (config change, no code).
 *   - PER-TENANT override:  set the `ai_feature.<key>` setting for the workspace via
 *     the feature-flag system (e.g. app('features')->enable('ai_feature.voice_analysis')).
 *
 * No code needs to change to enable or disable any of these features.
 */
return [
    'flags' => [
        'memory_engine'      => true,
        'cheating_detection' => true,
        'voice_analysis'     => false,
        'video_analysis'     => false,
        'multi_agent'        => true,
        'explainable_ai'     => true,
        'knowledge_engine'   => true,
        'prompt_optimizer'   => true,
        'workflow_builder'   => true,
    ],
];
