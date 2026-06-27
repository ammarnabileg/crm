<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\MaintenanceMode;
use Closure;

/**
 * Short-circuits the request with a 503 maintenance page while maintenance mode
 * is enabled. Super admins always pass through so they can never lock themselves
 * out, and the System pages plus auth/asset routes stay reachable so the toggle
 * can be flipped back off from the browser. An optionally configured allow-IP is
 * also let through.
 *
 * Not wired anywhere — the lead registers this in the middleware stack.
 */
final class CheckMaintenanceMode implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = new MaintenanceMode();

        if (! $maintenance->isEnabled()) {
            return $next($request);
        }

        // Super admins are never locked out of the dashboard.
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        $details = $maintenance->details();

        // An explicitly allow-listed IP keeps browsing while the site is down.
        if (! empty($details['allow_ip']) && $request->ip() === $details['allow_ip']) {
            return $next($request);
        }

        // Keep the toggle, auth flow and assets reachable so the site can be
        // brought back up (and the maintenance page's own styling loads).
        if ($this->isAllowedPath($request->path())) {
            return $next($request);
        }

        return Response::make(
            render('errors.maintenance', ['message' => $details['message']]),
            503
        )->header('Retry-After', '3600');
    }

    /**
     * Paths that must stay reachable even during maintenance.
     */
    private function isAllowedPath(string $path): bool
    {
        $path = '/' . ltrim($path, '/');

        $allowedPrefixes = ['/system', '/assets'];
        foreach ($allowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        $allowedExact = ['/login', '/logout'];

        return in_array($path, $allowedExact, true);
    }
}
