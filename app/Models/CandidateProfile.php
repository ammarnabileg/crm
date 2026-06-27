<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A candidate's global profile (docs/53 ATS). Unlike the rest of the recruitment
 * schema this table is NOT tenant-scoped — it is keyed by `user_id` and shared
 * across workspaces (one profile per person, surfaced to every tenant the
 * candidate applies to). Soft-deletable.
 */
final class CandidateProfile extends Model
{
    protected static string $table = 'candidate_profiles';
    protected static bool $tenantScoped = false;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'user_id', 'headline', 'summary', 'current_title', 'total_experience_years',
        'availability_id', 'notice_period_days', 'expected_salary',
        'expected_salary_currency_id', 'expected_salary_period_id',
        'nationality_country_id', 'residence_country_id', 'city', 'date_of_birth',
        'gender_id', 'is_open_to_work', 'is_searchable', 'profile_completeness',
    ];

    protected static array $casts = [
        'is_open_to_work'        => 'bool',
        'is_searchable'          => 'bool',
        'total_experience_years' => 'float',
        'notice_period_days'     => 'int',
        'expected_salary'        => 'float',
        'profile_completeness'   => 'int',
    ];
}
