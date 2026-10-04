<?php

declare(strict_types=1);

/*
 * Session configuration. Production-ready: storage is driver-based (file now,
 * redis/database can be registered later via SessionStoreFactory::extend), the
 * Secure cookie flag is auto-detected from the request scheme (reverse-proxy /
 * Cloudflare aware) unless explicitly overridden, and nothing is bound to PHP's
 * default system save_path. See docs/SECURITY_GUIDE.md and CONFIGURATION_GUIDE.md.
 *
 * Backward compatibility: SESSION_SECURE=true|false still forces the flag exactly
 * as before; the new default (auto) simply stops http/localhost from silently
 * dropping the cookie.
 */

return [
    // Storage driver. 'file' is built in; 'redis'/'database' are reserved for a
    // future SessionStoreFactory::extend() registration (no code change here).
    'driver' => (string) env('SESSION_DRIVER', 'file'),

    // Server-side session validity, in MINUTES. Drives session.gc_maxlifetime so
    // sessions are NOT reaped by PHP's default 24-minute window. Override the raw
    // GC seconds with SESSION_GC_MAXLIFETIME only if you really need to.
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'gc_maxlifetime' => env('SESSION_GC_MAXLIFETIME', null),

    // When true the cookie is a browser-session cookie (cleared on close); when
    // false it lives for `lifetime` minutes. Default keeps users signed in.
    'expire_on_close' => filter_var(env('SESSION_EXPIRE_ON_CLOSE', false), FILTER_VALIDATE_BOOL),

    // Absolute directory for the file driver. Created automatically on boot; kept
    // inside the app so deploys/GC of /var/lib/php/sessions never touch it.
    'files' => storage_path('sessions'),

    // Trust reverse-proxy / load-balancer / Cloudflare forwarding headers when
    // deciding whether the *original* request was HTTPS. Leave OFF unless the app
    // truly sits behind a trusted proxy — otherwise a client could spoof the
    // scheme. Enable in production behind Nginx/Cloudflare with TLS termination.
    'trust_proxy' => filter_var(env('TRUST_PROXY', false), FILTER_VALIDATE_BOOL),

    'cookie' => [
        'name' => (string) env('SESSION_COOKIE', 'hahireai_session'),
        'path' => (string) env('SESSION_COOKIE_PATH', '/'),
        // Empty string => host-only cookie (correct default; do NOT hardcode a domain).
        'domain' => (string) env('SESSION_DOMAIN', ''),
        // true | false | auto. 'auto' (default) => Secure only when the request is
        // HTTPS. 'true'/'false' force the flag regardless of scheme (override).
        'secure' => env('SESSION_SECURE', 'auto'),
        'http_only' => filter_var(env('SESSION_HTTP_ONLY', true), FILTER_VALIDATE_BOOL),
        'same_site' => (string) env('SESSION_SAMESITE', 'Lax'),
    ],
];
