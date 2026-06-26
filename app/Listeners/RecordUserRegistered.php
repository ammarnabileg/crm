<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Audit\AuditLogger;
use App\Events\UserRegistered;

/**
 * Records an audit entry when a user registers. Side effect of the
 * UserRegistered event (docs/47 EAS-6/EAS-7).
 */
final class RecordUserRegistered
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function __invoke(UserRegistered $event): void
    {
        $this->audit->log('user.registered', [
            'company_id'   => null,
            'user_id'      => (int) $event->user->getKey(),
            'subject_type' => 'user',
            'subject_id'   => (int) $event->user->getKey(),
            'description'  => 'Registered a new account',
        ]);
    }
}
