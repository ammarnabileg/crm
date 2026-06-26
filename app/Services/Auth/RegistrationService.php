<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Hash;
use App\DTOs\RegisterUserData;
use App\Events\UserRegistered;
use App\Models\Workspace;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;

/**
 * Application-layer use-case for registration. Orchestrates the domain via a
 * repository and a DTO, fires the UserRegistered event (audit/notifications are
 * listeners), and optionally provisions the registrant's first workspace. Holds no
 * SQL and no HTTP concern (docs/47 EAS-1..EAS-6).
 */
final class RegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WorkspaceService $workspaces,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    /**
     * @return array{user: User, workspace: ?Workspace}
     */
    public function register(RegisterUserData $data): array
    {
        $user = $this->users->create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
            'locale'   => $data->locale,
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        /** @var User $user */

        $this->events->dispatch(new UserRegistered($user));

        $workspace = null;
        if ($data->workspaceName !== null) {
            $workspace = $this->workspaces->create($user, $data->workspaceName);
        }

        return ['user' => $user, 'workspace' => $workspace];
    }
}
