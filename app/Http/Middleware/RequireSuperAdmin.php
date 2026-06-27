<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Restricts a route to platform super admins only.
 *
 * IMPORTANT — why this exists and is NOT `permission:system.manage`: the workspace
 * Owner role carries `permissions => '*'`, so every customer Owner is granted
 * `system.manage` too. Gating the platform operations area (`/system/*` — the .env
 * editor, database backups of EVERY tenant, the cross-tenant console) on that
 * permission therefore leaks platform-owner powers to ordinary customers — a
 * cross-tenant privilege escalation. Super-admin status is a GLOBAL role
 * (`roles.slug = super-admin`, `workspace_id IS NULL`) that the `*` wildcard cannot
 * grant, so it is the correct gate. A super admin needs no active workspace to pass
 * (platform ops are tenant-independent).
 */
final class RequireSuperAdmin implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['message' => 'Super-admin access is required.'], 403);
        }

        // Don't reveal the platform area to non-super-admins — bounce to the app.
        session()->flash('error', 'That area is restricted to platform administrators.');

        return Response::redirect(url('dashboard'));
    }
}
