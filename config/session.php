<?php

declare(strict_types=1);

return [
    'cookie'   => env('SESSION_COOKIE', 'halaops_session'),
    'lifetime' => (int) env('SESSION_LIFETIME', 7200),
    'secure'   => (bool) env('SESSION_SECURE', false),
];
