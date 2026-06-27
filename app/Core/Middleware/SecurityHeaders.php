<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Applies hardening headers to every response, including a Content-Security-Policy.
 * The CSP restricts everything to the same origin and forbids plugins, base-uri
 * hijacking and cross-origin framing/forms; it permits `'unsafe-inline'` for
 * script/style because the app uses inline styles (progress bar) and the install
 * console's inline script. `img-src` allows `data:` for the inline SVG favicon.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    private const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'        => '0',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy'  => self::CSP,
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
