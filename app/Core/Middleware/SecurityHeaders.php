<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Applies hardening headers to every response. CSP is intentionally
 * conservative but allows the compiled local assets plus the inline nonce-free
 * styles used by the install console.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'        => '0',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
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
