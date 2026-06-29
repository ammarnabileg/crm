<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;

/**
 * HTTP middleware contract. Registration is supported now; the execution
 * pipeline is wired in a later phase. See docs/ROUTING_GUIDE.md.
 */
interface Middleware
{
    /**
     * Handle the request and either short-circuit with a Response or call
     * $next($request) to continue the pipeline.
     */
    public function handle(Request $request, callable $next): Response;
}
