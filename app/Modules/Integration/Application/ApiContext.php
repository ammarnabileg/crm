<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Http\Request;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\Authorizer;

/**
 * The per-request authorization context for the API Gateway. Mirrors
 * WorkspaceContext, but the principal is an API token (Bearer) rather than a
 * session. Permissions are the token's member's effective workspace permissions
 * — the gateway is bound by the exact same RBAC as the UI
 * (docs/INTEGRATION_PLATFORM.md §3, PERMISSION_MODEL.md).
 */
final class ApiContext
{
    private bool $authenticated = false;
    private ?string $workspaceId = null;
    private ?string $userId = null;
    private ?string $tokenId = null;

    /** @var list<string> */
    private array $permissions = [];

    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly MembershipService $memberships,
        private readonly Authorizer $authorizer,
    ) {
    }

    /** Resolve the Bearer token and its member's permissions. */
    public function authenticate(Request $request): bool
    {
        $token = $this->bearerToken($request);
        if ($token === null) {
            return false;
        }

        $row = $this->tokens->authenticate($token);
        if ($row === null) {
            return false;
        }

        $workspaceId = (string) $row['workspace_id'];
        $userId = (string) $row['user_id'];

        // The token is only valid while its member still belongs to the workspace.
        $membership = $this->memberships->find($workspaceId, $userId);
        if ($membership === null) {
            return false;
        }

        $this->authenticated = true;
        $this->tokenId = (string) $row['id'];
        $this->workspaceId = $workspaceId;
        $this->userId = $userId;
        $this->permissions = $this->authorizer->permissionsForMembership((string) $membership['id']);

        return true;
    }

    public function authenticated(): bool
    {
        return $this->authenticated;
    }

    public function workspaceId(): ?string
    {
        return $this->workspaceId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function tokenId(): ?string
    {
        return $this->tokenId;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function can(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissions, true);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('authorization');
        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) === 1) {
            return trim($m[1]);
        }

        // Fallback for clients that cannot set Authorization.
        $alt = $request->header('x-api-key');

        return $alt !== null && $alt !== '' ? trim($alt) : null;
    }
}
