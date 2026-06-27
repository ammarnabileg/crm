<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Applies hardening headers to every response, including a strict, nonce-based
 * Content-Security-Policy. A fresh per-request nonce is bound (csp_nonce()) before
 * the response renders; only inline <script> tags carrying that nonce execute, so
 * `script-src` no longer needs `'unsafe-inline'` — injected inline scripts (the
 * classic XSS payload) are blocked by the browser even if they slip past escaping.
 * `style-src` keeps `'unsafe-inline'` for the handful of inline `style=""` attributes
 * (progress bars, swatches), which is low risk. `img-src` allows `data:` for the
 * inline SVG favicon. Everything else is same-origin; framing/forms/base-uri locked.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate + bind the nonce BEFORE rendering so views can stamp it onto
        // their legitimate inline scripts; reuse the same value in the header.
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp_nonce', $nonce);

        $response = $next($request);

        $csp = "default-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'self'; "
            . "form-action 'self'";

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'        => '0',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy'  => $csp,
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if ($response->getHeader($name) === null) {
                $response->header($name, $value);
            }
        }

        return $response;
    }
}
