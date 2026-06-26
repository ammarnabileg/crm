<?php

declare(strict_types=1);

return [
    // When false, the mailer logs messages to storage/logs instead of using
    // PHP mail(). Hosts with a working MTA can flip this on from settings.
    'enabled'      => (bool) env('MAIL_ENABLED', false),
    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@halaops.local'),
    'from_name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'HalaOps')),
];
