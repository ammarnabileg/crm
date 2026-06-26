<?php

declare(strict_types=1);

/**
 * Default values for per-tenant settings. A workspace overrides any of these from
 * its Settings screen; the stored value wins, otherwise these defaults apply.
 * Nothing the platform exposes as configurable is hard-coded in logic
 * (docs/47 EAS-9).
 */
return [
    'defaults' => [
        // Branding
        'brand.name'           => env('APP_NAME', 'HalaOps'),
        'brand.primary_color'  => '#2457eb',
        'brand.logo'           => '',

        // Localization
        'locale'               => env('APP_LOCALE', 'en'),
        'currency'             => env('APP_CURRENCY', 'SAR'),
        'timezone'             => env('APP_TIMEZONE', 'Asia/Riyadh'),

        // Notifications
        'mail.from_name'       => env('MAIL_FROM_NAME', 'HalaOps'),
        'notifications.digest' => 'instant', // instant | daily

        // Hiring defaults
        'hiring.default_pipeline' => 'applied,screening,interview,offer,hired,rejected',
    ],

    // TTL (seconds) for cached setting lookups.
    'cache_ttl' => 300,
];
