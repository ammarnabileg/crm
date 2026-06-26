<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Audit\AuditLogger;
use App\Events\CompanyCreated;

/**
 * Records an audit entry (with the new company's values) when a company is
 * provisioned. Side effect of the CompanyCreated event (docs/47 EAS-6/EAS-7).
 */
final class RecordCompanyCreated
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function __invoke(CompanyCreated $event): void
    {
        $company = $event->company;

        $this->audit->logChange('company.created', null, [
            'name'   => $company->name,
            'slug'   => $company->slug,
            'status' => $company->status,
        ], [
            'company_id'   => (int) $company->getKey(),
            'user_id'      => (int) $event->owner->getKey(),
            'subject_type' => 'company',
            'subject_id'   => (int) $company->getKey(),
            'description'  => 'Created company "' . $company->name . '"',
        ]);
    }
}
