<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Company;
use App\Models\User;

/**
 * Fired when a company (tenant) is provisioned. Listeners handle audit logging
 * and (later) provisioning side effects (e.g. search indexing, onboarding).
 */
final class CompanyCreated extends Event
{
    public function __construct(
        public readonly Company $company,
        public readonly User $owner,
    ) {
        parent::__construct();
    }
}
