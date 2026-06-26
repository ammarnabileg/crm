<?php

declare(strict_types=1);

use App\Core\Middleware\SecurityHeaders;
use App\Core\Middleware\VerifyCsrfToken;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ThrottleRequests;

/**
 * Route middleware aliases. Referenced by name in routes/web.php (e.g.
 * ->middleware('auth')) and resolved by the Router. The "alias:arg" form is
 * supported, e.g. 'permission:workspaces.manage' or 'throttle:5,60'.
 */
return [
    'security'   => SecurityHeaders::class,
    'csrf'       => VerifyCsrfToken::class,
    'auth'       => Authenticate::class,
    'guest'      => RedirectIfAuthenticated::class,
    'tenant'     => EnsureTenant::class,
    'permission' => RequirePermission::class,
    'throttle'   => ThrottleRequests::class,
];
