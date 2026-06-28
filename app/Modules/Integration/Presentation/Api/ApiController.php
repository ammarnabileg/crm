<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Presentation\Api;

use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Modules\Integration\Application\ApiContext;
use HaHireAI\Modules\Integration\Application\RateLimiter;
use HaHireAI\Modules\Recruitment\Application\JobService;

/**
 * The API Gateway — the single authenticated REST surface (`/api/v1`). Every
 * request is Bearer-authenticated, rate-limited, permission-gated, and
 * workspace-scoped, returning a consistent JSON envelope
 * (docs/INTEGRATION_PLATFORM.md §3, API_GUIDELINES.md).
 */
final class ApiController
{
    /** @var array{allowed: bool, limit: int, remaining: int, retry_after: int}|null */
    private ?array $rate = null;

    public function __construct(
        private readonly ApiContext $api,
        private readonly RateLimiter $limiter,
        private readonly JobService $jobs,
        private readonly Repository $config,
    ) {
    }

    /** Unauthenticated liveness of the API surface. */
    public function ping(): Response
    {
        return $this->ok(['status' => 'ok', 'api' => (string) $this->config->get('integration.api_version', 'v1')]);
    }

    /** Identity + effective permissions of the presented token. */
    public function me(Request $request): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }

        return $this->ok([
            'workspace_id' => $this->api->workspaceId(),
            'user_id' => $this->api->userId(),
            'token_id' => $this->api->tokenId(),
            'permissions' => $this->api->permissions(),
        ]);
    }

    public function listJobs(Request $request): Response
    {
        if (($guard = $this->guard($request, 'job.view')) !== null) {
            return $guard;
        }

        $jobs = array_map(
            fn (array $j): array => $this->presentJob($j),
            $this->jobs->listForWorkspace((string) $this->api->workspaceId()),
        );

        return $this->ok($jobs, ['count' => count($jobs)]);
    }

    public function showJob(Request $request, string $id): Response
    {
        if (($guard = $this->guard($request, 'job.view')) !== null) {
            return $guard;
        }

        $job = $this->jobs->find((string) $this->api->workspaceId(), $id);
        if ($job === null) {
            return $this->error(404, 'not_found', 'Job not found.');
        }

        return $this->ok($this->presentJob($job));
    }

    /**
     * Authenticate, rate-limit, then (optionally) authorize. Returns a Response
     * to short-circuit on failure, or null to proceed.
     */
    private function guard(Request $request, ?string $permission = null): ?Response
    {
        if (! $this->api->authenticate($request)) {
            return $this->error(401, 'unauthorized', 'A valid Bearer API token is required.');
        }

        $limit = (int) $this->config->get('integration.api_rate_limit', 120);
        $window = (int) $this->config->get('integration.api_rate_window', 60);
        $rate = $this->limiter->hit('api:token:' . $this->api->tokenId(), $limit, $window);

        if (! $rate['allowed']) {
            return $this->error(429, 'rate_limited', 'Rate limit exceeded.')
                ->withHeader('Retry-After', (string) $rate['retry_after'])
                ->withHeader('X-RateLimit-Limit', (string) $rate['limit'])
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        if ($permission !== null && ! $this->api->can($permission)) {
            return $this->error(403, 'forbidden', "Missing required permission [{$permission}].");
        }

        $this->rate = $rate;

        return null;
    }

    private function ok(mixed $data, array $meta = []): Response
    {
        $response = Response::json($meta === [] ? ['data' => $data] : ['data' => $data, 'meta' => $meta]);

        if ($this->rate !== null) {
            $response->withHeader('X-RateLimit-Limit', (string) $this->rate['limit'])
                ->withHeader('X-RateLimit-Remaining', (string) $this->rate['remaining']);
        }

        return $response;
    }

    private function error(int $status, string $code, string $message): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /** @param array<string, mixed> $job */
    private function presentJob(array $job): array
    {
        return [
            'id' => (string) $job['id'],
            'title' => (string) $job['title'],
            'status' => (string) $job['status'],
            'location' => $job['location'] ?? null,
            'employment_type' => $job['employment_type'] ?? null,
            'applications_count' => isset($job['applications_count']) ? (int) $job['applications_count'] : null,
            'public_token' => (string) ($job['public_token'] ?? ''),
            'created_at' => $job['created_at'] ?? null,
        ];
    }
}
