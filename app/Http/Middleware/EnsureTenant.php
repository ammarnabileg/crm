<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Guarantees an active tenant (company) context for tenant-scoped areas of the
 * app. A user with no active company is sent to the company-chooser/creation
 * flow; super admins operating platform-wide are allowed through.
 */
final class EnsureTenant implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()->hasTenant() || auth()->user()?->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['message' => 'No active company selected.'], 409);
        }

        return Response::redirect(url('companies/select'));
    }
}
