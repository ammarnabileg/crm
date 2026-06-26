<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Guarantees an active tenant (workspace) context for tenant-scoped areas of the
 * app. A user with no active workspace is sent to the workspace-chooser/creation
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
            return Response::json(['message' => 'No active workspace selected.'], 409);
        }

        return Response::redirect(url('workspaces/select'));
    }
}
