<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A typed rule attached to a blueprint section (docs/51 §6). `rule_type` is a
 * code-validated VARCHAR key (question_strategy | scoring | follow_up |
 * expected_skill | expected_behavior | evaluation_criteria) and `payload` carries
 * the rule's data (a strategy string, scoring guidance, a follow-up policy, a
 * skill/behavior name, a criterion). Consulted by the interview runtime to generate
 * questions and steer the conversation. Tenant-scoped.
 */
final class BlueprintSectionRule extends Model
{
    protected static string $table = 'blueprint_section_rules';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'section_id', 'rule_type', 'payload', 'sort_order',
    ];

    protected static array $casts = [
        'payload'    => 'array',
        'sort_order' => 'int',
    ];
}
