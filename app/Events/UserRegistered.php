<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;

/**
 * Fired when a new user account is created. Listeners handle side effects such
 * as audit logging and (later) a welcome notification.
 */
final class UserRegistered extends Event
{
    public function __construct(public readonly User $user)
    {
        parent::__construct();
    }
}
