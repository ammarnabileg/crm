<?php

declare(strict_types=1);

return [
    // When false, the mailer logs messages to storage/logs instead of using
    // PHP mail(). Hosts with a working MTA can flip this on from settings.
    'enabled'      => (bool) env('MAIL_ENABLED', false),
    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@halaops.local'),
    'from_name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'HalaOps')),

    // SMTP transport. OPTIONAL: when `host` is empty the Mailer falls back to PHP
    // mail()/log, so the app works with zero SMTP config. Defaults come from env;
    // the per-tenant Workspace Settings → Email tab overlays host/port/username/
    // password at request boot (Application::bootTenantContext). The secret is never
    // written to .env from the UI. `encryption`: tls (STARTTLS) | ssl | none.
    'smtp' => [
        'host'       => env('MAIL_SMTP_HOST', ''),
        'port'       => (int) env('MAIL_SMTP_PORT', 587),
        'username'   => env('MAIL_SMTP_USERNAME', ''),
        'password'   => env('MAIL_SMTP_PASSWORD', ''),
        'encryption' => env('MAIL_SMTP_ENCRYPTION', 'tls'),
        'timeout'    => (int) env('MAIL_SMTP_TIMEOUT', 15),
    ],
];
