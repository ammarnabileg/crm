<?php

declare(strict_types=1);

return [
    'name'    => env('APP_NAME', 'HalaOps'),
    'env'     => env('APP_ENV', 'production'),
    'debug'   => (bool) env('APP_DEBUG', false),
    'url'     => env('APP_URL', ''),
    'key'     => env('APP_KEY', ''),

    'timezone'         => env('APP_TIMEZONE', 'Asia/Riyadh'),
    'locale'           => env('APP_LOCALE', 'en'),
    'fallback_locale'  => env('APP_FALLBACK_LOCALE', 'en'),
    'supported_locales' => ['en', 'ar'],

    // Bumped to bust browser caches when shipping new compiled assets.
    'asset_version' => env('ASSET_VERSION', '1.0.0'),

    'currency' => env('APP_CURRENCY', 'SAR'),
];
