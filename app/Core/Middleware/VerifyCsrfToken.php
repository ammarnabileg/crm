<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Verifies the CSRF token on every state-changing request. The token is read
 * from the `_token` form field or the `X-CSRF-TOKEN` header and compared in
 * constant time against the session token.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        $sessionToken = session()->token();
        $provided = (string) ($request->input('_token') ?? $request->header('x-csrf-token') ?? '');

        if ($provided === '' || ! hash_equals($sessionToken, $provided)) {
            throw new HttpException(419, 'Your session has expired. Please refresh and try again.');
        }

        return $next($request);
    }
}
