<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Core\Hash;
use App\DTOs\RegisterUserData;
use App\Events\UserRegistered;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\CompanyService;

/**
 * Application-layer use-case for registration. Orchestrates the domain via a
 * repository and a DTO, fires the UserRegistered event (audit/notifications are
 * listeners), and optionally provisions the registrant's first company. Holds no
 * SQL and no HTTP concern (docs/47 EAS-1..EAS-6).
 */
final class RegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CompanyService $companies,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    /**
     * @return array{user: User, company: ?Company}
     */
    public function register(RegisterUserData $data): array
    {
        $user = $this->users->create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
            'locale'   => $data->locale,
            'status'   => 'active',
        ]);
        /** @var User $user */

        $this->events->dispatch(new UserRegistered($user));

        $company = null;
        if ($data->companyName !== null) {
            $company = $this->companies->create($user, $data->companyName);
        }

        return ['user' => $user, 'company' => $company];
    }
}
