<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seeds the configuration-driven Lookup Category Registry (docs/database/12 F3):
 * every `lookup_categories` + system-default `lookup_values` that the schema's
 * `*_id → lookup_values` FKs target. This is what makes the platform ENUM-free —
 * statuses/types/levels/channels are DATA, editable per tenant, not code.
 *
 * System scope (workspace_id NULL, is_system=1). Idempotent (keyed by category+key)
 * so the installer can re-run it. Labels are derived from the key unless given.
 *
 * Returned as an anonymous object (loaded via require) so it works during install.
 */
return new class {
    /**
     * category key => [value keys...]. Labels are auto-titled from the key; the
     * first value is marked is_default. Shared categories are defined once (F3).
     */
    private array $registry = [
        // Workspace / Settings
        'integration_type'   => ['oauth', 'api_key', 'webhook'],
        'domain_type'        => ['career_site', 'app', 'api'],
        // RBAC
        'membership_status'  => ['active', 'invited', 'suspended'],
        'role_history_action' => ['created', 'updated', 'deleted', 'assigned', 'revoked'],
        'permission_history_action' => ['granted', 'revoked', 'updated'],
        'policy_effect'      => ['allow', 'deny'],
        // Auth
        'login_failure_reason' => ['bad_password', 'unknown_user', 'locked', 'mfa_failed', 'disabled'],
        'device_type'        => ['web', 'mobile', 'tablet', 'desktop', 'api'],
        'mfa_method_type'    => ['totp', 'sms', 'email', 'recovery_code'],
        // Billing
        'billing_interval'   => ['monthly', 'yearly'],
        'coupon_type'        => ['percentage', 'fixed'],
        'transaction_type'   => ['charge', 'refund', 'adjustment', 'payout', 'credit'],
        'usage_metric'       => ['interviews', 'ai_tokens', 'storage_mb', 'members', 'jobs'],
        'tax_type'           => ['none', 'vat'],
        // Jobs (salary_period/skill_level/language_proficiency shared — see below)
        'employment_type'    => ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'],
        'experience_level'   => ['entry', 'junior', 'mid', 'senior', 'lead', 'principal', 'executive'],
        'salary_period'      => ['hourly', 'daily', 'weekly', 'monthly', 'yearly'],
        'job_question_type'  => ['text', 'multiple_choice', 'boolean', 'rating', 'file'],
        'job_criterion_type' => ['skill', 'experience', 'education', 'language', 'culture', 'custom'],
        'pipeline_stage_type' => ['sourced', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected'],
        // Candidates
        'skill_level'        => ['beginner', 'intermediate', 'advanced', 'expert'],
        'language_proficiency' => ['basic', 'conversational', 'fluent', 'native'],
        'social_platform'    => ['linkedin', 'github', 'twitter', 'website', 'portfolio', 'behance', 'dribbble'],
        'document_type'      => ['resume', 'cover_letter', 'certificate', 'portfolio', 'id', 'other'],
        'availability'       => ['immediate', 'two_weeks', 'one_month', 'negotiable'],
        'education_level'    => ['high_school', 'diploma', 'bachelor', 'master', 'doctorate'],
        'gender'             => ['male', 'female', 'unspecified'],
        'seniority'          => ['individual_contributor', 'manager', 'director', 'vp', 'c_level'],
        // Applications / Interviews
        'application_source' => ['career_site', 'referral', 'linkedin', 'job_board', 'agency', 'direct'],
        'application_decision' => ['advance', 'reject', 'hold', 'hire'],
        'interview_type'     => ['phone', 'video', 'onsite', 'technical', 'hr', 'panel', 'ai'],
        'interview_mode'     => ['in_person', 'remote', 'hybrid'],
        'participant_role'   => ['interviewer', 'candidate', 'observer', 'coordinator'],
        'participant_response' => ['accepted', 'declined', 'tentative', 'no_response'],
        'media_type'         => ['audio', 'video', 'screen', 'image'],
        'answer_type'        => ['text', 'audio', 'video', 'code', 'file'],
        'session_status'     => ['pending', 'active', 'completed', 'failed'],
        // AI / Notifications (notification_channel is a catalog table, not a lookup)
        'ai_capability'      => ['text_generation', 'transcription', 'analysis', 'scoring', 'avatar', 'embedding'],
        'notification_type'  => ['system', 'application', 'interview', 'offer', 'billing', 'mention', 'reminder'],
        // HR / Talent
        'team_role'          => ['lead', 'member', 'manager'],
        'evaluation_field_type' => ['rating', 'text', 'boolean', 'select', 'scorecard'],
        'recommendation'     => ['strong_yes', 'yes', 'neutral', 'no', 'strong_no'],
        'approval_step_status' => ['pending', 'approved', 'rejected', 'skipped'],
        'pool_visibility'    => ['private', 'team', 'workspace', 'public'],
        'meeting_mode'       => ['in_person', 'video', 'phone'],
        // Files / Analytics
        'file_visibility'    => ['private', 'workspace', 'public'],
        'performance_subject' => ['recruiter', 'job', 'pipeline', 'source'],
        // Cross-cutting
        'note_types'         => ['general', 'interview', 'internal', 'system'],
        'user_status'        => ['active', 'suspended', 'pending'],
    ];

    public function run(Database $db): void
    {
        $now = now();

        foreach ($this->registry as $categoryKey => $values) {
            $catId = $db->table('lookup_categories')->whereNull('workspace_id')->where('key', '=', $categoryKey)->value('id');
            if ($catId === null) {
                $catId = $db->table('lookup_categories')->insertGetId([
                    'uuid'        => $this->uuid($db),
                    'workspace_id' => null,
                    'key'         => $categoryKey,
                    'label'       => $this->title($categoryKey),
                    'is_system'   => 1,
                    'is_active'   => 1,
                    'sort_order'  => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            $catId = (int) $catId;

            $order = 0;
            foreach ($values as $valueKey) {
                $order++;
                $exists = $db->table('lookup_values')
                    ->where('category_id', '=', $catId)
                    ->whereNull('workspace_id')
                    ->where('key', '=', $valueKey)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $db->table('lookup_values')->insert([
                    'uuid'        => $this->uuid($db),
                    'category_id' => $catId,
                    'workspace_id' => null,
                    'key'         => $valueKey,
                    'label'       => $this->title($valueKey),
                    'sort_order'  => $order,
                    'is_default'  => $order === 1 ? 1 : 0,
                    'is_system'   => 1,
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    private function title(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }
};
