<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Guarantees an active tenant (workspace) context for tenant-scoped areas of the
 * app. A user with no active workspace — INCLUDING a super admin straight after
 * install — is sent to the workspace-chooser/creation flow rather than allowed into
 * tenant-scoped pages that would then explode (every tenant-scoped Model/Settings
 * read throws without a tenant). Platform-wide super-admin operations live under
 * `/system/*`, which are gated by `permission:system.manage` and are NOT behind this
 * middleware, so a workspace-less super admin keeps full platform access.
 */
final class EnsureTenant implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenant()->hasTenant()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['message' => 'No active workspace selected.'], 409);
        }

        return Response::redirect(url('workspaces/select'));
    }
}
