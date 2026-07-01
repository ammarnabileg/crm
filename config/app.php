<?php

declare(strict_types=1);

/*
 * Application configuration. Values come from the environment; no secrets and
 * no logic live here. See docs/CONFIGURATION_GUIDE.md.
 */

return [
    'name' => env('APP_NAME', 'HaHireAI'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'key' => env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'log_level' => env('LOG_LEVEL', 'info'),
];
