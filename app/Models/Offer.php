<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A job offer to a candidate (docs/53 ATS). Status flows draft→pending_approval→
 * approved→sent→accepted/declined/rescinded/expired via `offer_statuses`. Approval
 * steps live in `offer_approvals`. Tenant-scoped, soft-deletable.
 */
final class Offer extends Model
{
    protected static string $table = 'offers';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'application_id', 'offer_status_id', 'candidate_user_id', 'job_title',
        'employment_type_id', 'salary_amount', 'currency_id', 'salary_period_id',
        'bonus_amount', 'equity', 'start_date', 'expires_at', 'sent_at', 'responded_at',
        'notes', 'created_by',
    ];

    protected static array $casts = [
        'salary_amount' => 'float',
        'bonus_amount'  => 'float',
    ];

    public function statusKey(): ?string
    {
        return $this->offer_status_id !== null
            ? (string) self::db()->table('offer_statuses')->where('id', '=', (int) $this->offer_status_id)->value('key')
            : null;
    }
}
