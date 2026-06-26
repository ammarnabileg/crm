<?php

declare(strict_types=1);

/**
 * Feature flag defaults. Each feature can be toggled per tenant from settings
 * without a code change (docs/47 EAS-9). The value here is the default when a
 * tenant has not overridden it.
 */
return [
    'ai_interviews'   => true,
    'jobs'            => true,
    'applications'    => true,
    'billing'         => true,
    'notifications'   => true,
    'api'             => false, // enabled when the API module ships
];
