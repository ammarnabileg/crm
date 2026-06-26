<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Keeps authenticated users away from guest-only pages (login, register).
 */
final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }
}
