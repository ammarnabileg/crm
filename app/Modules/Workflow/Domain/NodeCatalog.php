<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Domain;

/**
 * The node catalog — the single source of truth shared by the visual builder
 * (sidebar, node cards, config panels, variable pickers, the natural-language
 * summary) and the engine (executor dispatch). Pure data: no logic, no I/O.
 *
 * Every node declares its category, kind, label, icon, a config schema the UI
 * renders as plain form fields (NO JSON, NO code, NO expressions), and — for
 * triggers — the existing domain/audit event it binds to. Action nodes are
 * executed by NodeExecutors that call ONLY existing services/contracts.
 */
final class NodeCatalog
{
    /** Sidebar order (docs/WORKFLOW_NODES.md). */
    public const CATEGORIES = [
        'Triggers', 'Actions', 'Conditions', 'AI', 'Recruitment', 'Workspace',
        'Users', 'Notifications', 'Database', 'Files', 'Time', 'Logic',
        'Variables', 'Integrations', 'Utilities',
    ];

    /**
     * @return list<array<string,mixed>> every node type the builder offers
     */
    public static function nodes(): array
    {
        return [
            // ── Triggers — bound to events that already exist in the system ─────
            ...self::triggers(),
            // ── Conditions & Logic ───────────────────────────────
            ...self::logic(),
            // ── Actions across modules (existing services/contracts only) ───────
            ...self::actions(),
        ];
    }

    /** @return array<string,array<string,mixed>> nodes keyed by type, for the executor/validator. */
    public static function byType(): array
    {
        $map = [];
        foreach (self::nodes() as $n) {
            $map[(string) $n['type']] = $n;
        }

        return $map;
    }

    /** @return list<array{event:string,type:string,label:string}> trigger→event bindings the engine subscribes to. */
    public static function triggerBindings(): array
    {
        $out = [];
        foreach (self::triggers() as $t) {
            if (! empty($t['event'])) {
                $out[] = ['event' => (string) $t['event'], 'type' => (string) $t['type'], 'label' => (string) $t['label']];
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function triggers(): array
    {
        // Recruitment/workspace triggers bind to the existing AUDIT actions every
        // controller already records — surfaced on the bus by AuditTriggerBridge,
        // so NO existing service/controller is modified.
        $t = static fn (string $type, string $label, string $desc, string $event, array $outputs = []): array => [
            'type' => $type, 'category' => 'Triggers', 'kind' => 'trigger',
            'label' => $label, 'description' => $desc, 'icon' => 'bolt',
            'event' => $event, 'config' => [], 'outputs' => $outputs,
        ];
        $candidateOut = ['candidate_name', 'candidate_email', 'job_title', 'workspace_id'];

        return [
            $t('trigger.candidate_applied', 'Candidate Applied', 'A candidate applies to a job', 'application.submitted', $candidateOut),
            $t('trigger.interview_started', 'Interview Scheduled', 'An interview is scheduled', 'audit.recruitment.interview.scheduled', $candidateOut),
            $t('trigger.interview_finished', 'Interview Finished', 'An interview is completed', 'audit.recruitment.interview.evaluated', [...$candidateOut, 'ai_score']),
            $t('trigger.pipeline_changed', 'Pipeline Changed', 'A candidate moves stage', 'audit.recruitment.application.status_changed', [...$candidateOut, 'to_status']),
            $t('trigger.candidate_hired', 'Candidate Hired', 'A candidate is hired (add an If on status)', 'audit.recruitment.application.status_changed', [...$candidateOut, 'to_status']),
            $t('trigger.candidate_rejected', 'Candidate Rejected', 'A candidate is rejected (add an If on status)', 'audit.recruitment.application.status_changed', [...$candidateOut, 'to_status']),
            $t('trigger.offer_sent', 'Offer Sent', 'An offer is sent', 'audit.recruitment.offer.sent', $candidateOut),
            $t('trigger.offer_accepted', 'Offer Accepted', 'A candidate accepts an offer', 'audit.recruitment.offer.accepted', $candidateOut),
            $t('trigger.offer_declined', 'Offer Declined', 'A candidate declines an offer', 'audit.recruitment.offer.declined', $candidateOut),
            $t('trigger.workspace_created', 'Workspace Created', 'A workspace is created', 'audit.workspaces.workspace.created', ['workspace_id']),
            $t('trigger.workspace_suspended', 'Workspace Suspended', 'A workspace is suspended', 'audit.platform.workspace.suspended', ['workspace_id']),
            $t('trigger.workspace_restored', 'Workspace Restored', 'A workspace is restored', 'audit.platform.workspace.activated', ['workspace_id']),
            $t('trigger.user_joined', 'User Joined Workspace', 'A member joins a workspace', 'audit.memberships.invitation.accepted', ['workspace_id', 'user_id']),
            $t('trigger.subscription_renewed', 'Subscription Renewed', 'A subscription renews', 'audit.billing.subscription.renewed', ['workspace_id']),
            $t('trigger.payment_failed', 'Payment Failed', 'A payment attempt fails', 'audit.billing.payment.failed', ['workspace_id', 'amount', 'reason']),
            // First Impression Engine (zero-AI gate). The orchestrator publishes
            // these directly on the bus with the full report headline as outputs,
            // so a workflow can react / notify / route on the result with no AI.
            $t('trigger.first_impression_completed', 'First Impression Completed', 'The zero-AI first-impression review finishes', 'first_impression.completed', $fiOut = ['overall_score', 'threshold', 'passed', 'decision', 'job_id', 'report_id', 'application_id', 'candidate_user_id']),
            $t('trigger.first_impression_passed', 'First Impression Passed', 'An applicant reaches the minimum score (continues to AI)', 'first_impression.passed', $fiOut),
            $t('trigger.first_impression_failed', 'First Impression Failed', 'An applicant is filtered before the AI interview', 'first_impression.failed', $fiOut),
            $t('trigger.first_impression_override', 'First Impression Override', 'HR overrides a filtered decision to allow the AI interview', 'first_impression.override', ['report_id', 'application_id', 'job_id', 'candidate_user_id']),
            [
                'type' => 'trigger.schedule', 'category' => 'Triggers', 'kind' => 'trigger',
                'label' => 'Schedule', 'description' => 'Run on a schedule', 'icon' => 'clock',
                'event' => 'workflow.schedule.tick', 'config' => [
                    ['key' => 'cadence', 'label' => 'Run', 'type' => 'select', 'options' => ['hourly', 'daily', 'weekly']],
                ], 'outputs' => [],
            ],
            [
                'type' => 'trigger.webhook', 'category' => 'Triggers', 'kind' => 'trigger',
                'label' => 'Webhook', 'description' => 'Run when a unique URL is called', 'icon' => 'globe',
                'event' => '', 'config' => [], 'outputs' => ['payload'],
            ],
            [
                'type' => 'trigger.manual', 'category' => 'Triggers', 'kind' => 'trigger',
                'label' => 'Manual Trigger', 'description' => 'Run on demand from the builder', 'icon' => 'play',
                'event' => '', 'config' => [], 'outputs' => [],
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function logic(): array
    {
        $opOptions = ['equals', 'not_equals', 'contains', 'greater', 'less', 'exists', 'empty'];

        return [
            [
                'type' => 'condition.if', 'category' => 'Conditions', 'kind' => 'condition',
                'label' => 'If / Else', 'description' => 'Branch on a field comparison', 'icon' => 'branch',
                'config' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'variable'],
                    ['key' => 'op', 'label' => 'Is', 'type' => 'select', 'options' => $opOptions],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],
                ],
                'branches' => ['true', 'false'], 'outputs' => [],
            ],
            [
                'type' => 'condition.switch', 'category' => 'Conditions', 'kind' => 'condition',
                'label' => 'Switch', 'description' => 'Route by a field value', 'icon' => 'branch',
                'config' => [
                    ['key' => 'field', 'label' => 'Field', 'type' => 'variable'],
                    ['key' => 'cases', 'label' => 'Cases (comma-separated)', 'type' => 'text'],
                ],
                'branches' => ['match', 'default'], 'outputs' => [],
            ],
            [
                'type' => 'logic.filter', 'category' => 'Logic', 'kind' => 'condition',
                'label' => 'Filter (AND/OR)', 'description' => 'Continue only if conditions pass', 'icon' => 'funnel',
                'config' => [
                    ['key' => 'mode', 'label' => 'Match', 'type' => 'select', 'options' => ['all (AND)', 'any (OR)']],
                    ['key' => 'field', 'label' => 'Field', 'type' => 'variable'],
                    ['key' => 'op', 'label' => 'Is', 'type' => 'select', 'options' => $opOptions],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],
                ],
                'branches' => ['true', 'false'], 'outputs' => [],
            ],
            [
                'type' => 'variables.set', 'category' => 'Variables', 'kind' => 'action',
                'label' => 'Set Variable', 'description' => 'Store a value for later steps', 'icon' => 'variable',
                'config' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text'],
                ], 'outputs' => [],
            ],
            [
                'type' => 'logic.formula', 'category' => 'Logic', 'kind' => 'action',
                'label' => 'Formula / Code', 'description' => 'Compute a value with a safe expression (no raw code runs on the server)', 'icon' => 'variable',
                'config' => [
                    ['key' => 'name', 'label' => 'Save result as', 'type' => 'text'],
                    ['key' => 'expression', 'label' => 'Expression', 'type' => 'formula'],
                ], 'outputs' => [],
            ],
        ];
    }

    /** @return list<array<string,mixed>> action nodes (executed via existing services/contracts). */
    private static function actions(): array
    {
        $n = static fn (string $type, string $cat, string $label, string $desc, string $icon, array $config = [], array $outputs = []): array => [
            'type' => $type, 'category' => $cat, 'kind' => 'action',
            'label' => $label, 'description' => $desc, 'icon' => $icon, 'config' => $config, 'outputs' => $outputs,
        ];
        $varField = static fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'type' => 'variable'];
        $text = static fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'type' => 'text'];

        return [
            // AI — via the existing AI Engine (never embeds AI/providers). Each
            // declares the variable(s) it produces, so later nodes can read them.
            $n('ai.summary', 'AI', 'Generate Summary', 'Summarise with AI', 'sparkles', [$varField('subject', 'About')], ['summary']),
            $n('ai.evaluate_interview', 'AI', 'Evaluate Interview', 'Score an interview with AI', 'sparkles', [$varField('interview', 'Interview')], ['ai_score', 'recommendation']),
            $n('ai.rank_candidate', 'AI', 'Rank Candidate', 'Rank a candidate with AI', 'sparkles', [$varField('candidate', 'Candidate')], ['rank', 'score']),
            $n('ai.generate_questions', 'AI', 'Generate Questions', 'Draft interview questions', 'sparkles', [$text('topic', 'Topic')], ['questions']),
            $n('ai.translate', 'AI', 'Translate', 'Translate text', 'sparkles', [$varField('text', 'Text'), $text('to', 'To language')], ['translation']),
            $n('ai.summarize_cv', 'AI', 'Summarize CV', 'Summarise a CV', 'sparkles', [$varField('candidate', 'Candidate')], ['cv_summary']),
            $n('ai.recommendation', 'AI', 'Recommendation', 'AI hiring recommendation', 'sparkles', [$varField('candidate', 'Candidate')], ['recommendation']),
            $n('ai.decision', 'AI', 'Decision', 'AI advisory decision', 'sparkles', [$varField('subject', 'About')], ['decision']),
            $n('ai.skill_extraction', 'AI', 'Skill Extraction', 'Extract skills from text', 'sparkles', [$varField('text', 'Text')], ['skills']),

            // Recruitment — via the RecruitmentActions contract (existing services).
            $n('recruitment.move_candidate', 'Recruitment', 'Move Candidate', 'Move to a pipeline stage', 'users', [$varField('application_id', 'Application'), $text('stage', 'Stage')]),
            $n('recruitment.assign_recruiter', 'Recruitment', 'Assign Recruiter', 'Assign a recruiter', 'users', [$varField('application_id', 'Application'), $varField('user_id', 'Recruiter')]),
            $n('recruitment.schedule_interview', 'Recruitment', 'Schedule Interview', 'Schedule an AI/human interview', 'calendar', [$varField('application_id', 'Application'), ['key' => 'kind', 'label' => 'Type', 'type' => 'select', 'options' => ['ai', 'human']]]),
            $n('recruitment.reject_candidate', 'Recruitment', 'Reject Candidate', 'Reject an application', 'users', [$varField('application_id', 'Application')]),
            $n('recruitment.hire_candidate', 'Recruitment', 'Hire Candidate', 'Mark as hired', 'users', [$varField('application_id', 'Application')]),
            $n('recruitment.create_offer', 'Recruitment', 'Create Offer', 'Create an offer', 'document', [$varField('application_id', 'Application'), $text('salary', 'Salary')], ['offer_id']),
            $n('recruitment.send_offer', 'Recruitment', 'Send Offer', 'Send an offer', 'document', [$varField('offer_id', 'Offer')]),
            $n('recruitment.archive_candidate', 'Recruitment', 'Archive Candidate', 'Archive an application', 'archive', [$varField('application_id', 'Application')]),
            $n('recruitment.add_to_talent_pool', 'Recruitment', 'Add To Talent Pool', 'Add to a talent pool', 'users', [$varField('user_id', 'Candidate'), $text('pool', 'Pool')]),
            $n('recruitment.create_human_interview', 'Recruitment', 'Create Human Interview', 'Schedule a panel interview', 'calendar', [$varField('application_id', 'Application')]),
            $n('recruitment.update_stage', 'Recruitment', 'Update Pipeline Stage', 'Set the pipeline stage', 'list', [$varField('application_id', 'Application'), $text('stage', 'Stage')]),

            // Workspace — via existing TaskBoard / membership / notifications.
            $n('workspace.create_task', 'Workspace', 'Create Task', 'Create a workspace task', 'check', [$text('title', 'Title'), $varField('assignee', 'Assignee')]),
            $n('workspace.assign_user', 'Workspace', 'Assign User', 'Assign a user to a task', 'users', [$varField('task_id', 'Task'), $varField('user_id', 'User')]),
            $n('workspace.invite_member', 'Workspace', 'Invite Member', 'Invite a member by email', 'mail', [$text('email', 'Email')]),
            $n('workspace.create_notification', 'Workspace', 'Create Notification', 'Notify a user in-app', 'bell', [$varField('user_id', 'User'), $text('title', 'Title')]),
            $n('workspace.create_activity', 'Workspace', 'Create Activity', 'Record an activity entry', 'clock', [$text('message', 'Message')]),

            // Users
            $n('users.notify', 'Users', 'Notify User', 'Send an in-app notification', 'bell', [$varField('user_id', 'User'), $text('title', 'Title')]),

            // Notifications
            $n('notify.email', 'Notifications', 'Email', 'Send an email', 'mail', [$varField('to', 'To'), $text('subject', 'Subject'), $text('body', 'Body')]),
            $n('notify.in_app', 'Notifications', 'In-App', 'Send an in-app notification', 'bell', [$varField('user_id', 'User'), $text('title', 'Title')]),
            $n('notify.webhook', 'Notifications', 'Webhook', 'POST to a URL', 'globe', [$text('url', 'URL')]),
            $n('notify.slack', 'Notifications', 'Slack', 'Post to Slack (via webhook)', 'globe', [$text('url', 'Webhook URL'), $text('message', 'Message')]),
            $n('notify.teams', 'Notifications', 'Teams', 'Post to Teams (via webhook)', 'globe', [$text('url', 'Webhook URL'), $text('message', 'Message')]),

            // Database — Dynamic Collections (no raw SQL).
            $n('db.create_record', 'Database', 'Create Record', 'Add a record to a collection', 'database', [$text('collection', 'Collection'), $text('fields', 'Fields')], ['record_id']),
            $n('db.update_record', 'Database', 'Update Record', 'Update a record', 'database', [$text('collection', 'Collection'), $varField('record_id', 'Record'), $text('fields', 'Fields')]),
            $n('db.delete_record', 'Database', 'Delete Record', 'Delete a record', 'database', [$text('collection', 'Collection'), $varField('record_id', 'Record')]),
            $n('db.archive_record', 'Database', 'Archive Record', 'Archive a record', 'database', [$text('collection', 'Collection'), $varField('record_id', 'Record')]),
            $n('db.restore_record', 'Database', 'Restore Record', 'Restore an archived record', 'database', [$text('collection', 'Collection'), $varField('record_id', 'Record')]),
            $n('db.find_record', 'Database', 'Find Record', 'Find a record', 'database', [$text('collection', 'Collection'), $text('match', 'Match')], ['record']),
            $n('db.count_records', 'Database', 'Count Records', 'Count records', 'database', [$text('collection', 'Collection')], ['count']),
            $n('db.upsert_record', 'Database', 'Upsert Record', 'Create or update a record', 'database', [$text('collection', 'Collection'), $text('fields', 'Fields')]),

            // Files
            $n('files.attach', 'Files', 'Attach File', 'Attach a stored file to an entity', 'document', [$varField('file_id', 'File'), $varField('entity_id', 'Entity')]),

            // Time
            $n('time.delay', 'Time', 'Delay', 'Wait a fixed time', 'clock', [$text('minutes', 'Minutes')]),
            $n('time.wait_until', 'Time', 'Wait Until', 'Wait until a date/time', 'clock', [$text('until', 'Until (YYYY-MM-DD HH:MM)')]),
            $n('time.business_hours', 'Time', 'Business Hours', 'Continue only in business hours', 'clock', []),
            $n('time.retry', 'Time', 'Retry', 'Retry the previous step', 'refresh', [$text('times', 'Times')]),
            $n('time.timeout', 'Time', 'Timeout', 'Fail after a timeout', 'clock', [$text('minutes', 'Minutes')]),

            // Integrations
            $n('integration.http', 'Integrations', 'HTTP Request', 'Call an external API', 'globe', [$text('url', 'URL'), ['key' => 'method', 'label' => 'Method', 'type' => 'select', 'options' => ['GET', 'POST']]], ['response', 'status']),

            // Utilities
            $n('util.log', 'Utilities', 'Log', 'Write a log line', 'document', [$text('message', 'Message')]),
            $n('util.audit', 'Utilities', 'Audit', 'Record an audit entry', 'shield', [$text('message', 'Message')]),
            $n('util.noop', 'Utilities', 'No-op', 'Do nothing (placeholder)', 'dot', []),
        ];
    }
}
