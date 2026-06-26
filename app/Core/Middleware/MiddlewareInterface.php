<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

interface MiddlewareInterface
{
    /**
     * Handle the request and either short-circuit with a Response or pass it
     * down the pipeline via $next($request).
     */
    public function handle(Request $request, Closure $next): Response;
}
