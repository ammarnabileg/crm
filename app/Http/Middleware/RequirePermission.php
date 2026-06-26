<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Authorises the current user against one or more permissions. Used as
 * 'permission:companies.manage' or 'permission:users.view,users.update'
 * (any-of semantics). Authentication is assumed to run before this.
 */
final class RequirePermission implements MiddlewareInterface
{
    /** @var string[] */
    private array $permissions;

    public function __construct(string $permissions = '')
    {
        $this->permissions = array_filter(array_map('trim', explode(',', $permissions)));
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return Response::redirect(url('login'));
        }

        $allowed = false;
        foreach ($this->permissions as $permission) {
            if (access()->allows($permission)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
