<?php

declare(strict_types=1);

/*
 * Integration Platform settings (docs/INTEGRATION_PLATFORM.md).
 */

return [
    // API Gateway version exposed under /api/{version}.
    'api_version' => 'v1',

    // Per-token fixed-window rate limit for the API Gateway.
    'api_rate_limit' => (int) (env('API_RATE_LIMIT', 120)),
    'api_rate_window' => 60, // seconds
];
