<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Ensures a user is authenticated. Guests are redirected to the login page
 * (with the intended URL remembered) or receive 401 for JSON requests.
 */
final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            if ($request->wantsJson()) {
                return Response::json(['message' => 'Unauthenticated.'], 401);
            }

            session()->put('url.intended', $request->fullUrl());

            return Response::redirect(url('login'));
        }

        return $next($request);
    }
}
