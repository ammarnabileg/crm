<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Support\RateLimiter;
use Closure;

/**
 * Fixed-window rate limiter keyed by route + client IP. Used as
 * 'throttle:5,60' (5 requests per 60 seconds). Backed by the file cache so it
 * works without Redis on shared hosting.
 */
final class ThrottleRequests implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(string $config = '60,60')
    {
        [$max, $decay] = array_pad(explode(',', $config), 2, '60');
        $this->maxAttempts = max(1, (int) $max);
        $this->decaySeconds = max(1, (int) $decay);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'throttle:' . sha1($request->method() . '|' . $request->path() . '|' . $request->ip());
        $limiter = new RateLimiter(storage_path('cache'));

        if ($limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $limiter->availableIn($key);

            if ($request->wantsJson()) {
                return Response::json(['message' => 'Too many requests.'], 429)
                    ->header('Retry-After', (string) $retryAfter);
            }

            throw new HttpException(429, "Too many attempts. Please try again in {$retryAfter} seconds.");
        }

        $limiter->hit($key, $this->decaySeconds);

        return $next($request);
    }
}
