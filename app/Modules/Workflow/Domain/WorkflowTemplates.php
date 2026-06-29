<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Domain;

/**
 * Ready-made workflow templates — one-click starting points the builder can clone.
 * Each is a real node graph ({nodes, edges}) built from {@see NodeCatalog} types, so
 * "Use template" just saves it as a normal workflow the user can then tweak.
 * Pure data: no logic, no I/O.
 */
final class WorkflowTemplates
{
    /** @return list<array{key:string,name:string,description:string,category:string,nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}> */
    public static function all(): array
    {
        return [
            self::tpl('ai-screen', 'AI-screen new applicants', 'When someone applies, summarise them with AI and create a review task.', 'Screening', [
                ['ai.summary', ['subject' => '{{candidate_name}}']],
                ['workspace.create_task', ['title' => 'Review {{candidate_name}} for {{job_title}}']],
            ]),
            self::tpl('welcome-applicant', 'Welcome new applicants', 'Acknowledge every applicant instantly and log the activity.', 'Candidate experience', [
                ['users.notify', ['user_id' => '{{user_id}}', 'title' => 'Thanks for applying to {{job_title}}']],
                ['util.audit', ['message' => 'Welcomed {{candidate_name}}']],
            ]),
            self::tpl('auto-reject-low', 'Auto-reject low interview scores', 'After an interview, if the AI score is below 50, reject and notify.', 'Pipeline', [
                ['condition.if', ['field' => 'ai_score', 'op' => 'less', 'value' => '50']],
                ['recruitment.reject_candidate', ['application_id' => '{{application_id}}']],
                ['users.notify', ['user_id' => '{{user_id}}', 'title' => 'Update on your application']],
            ]),
            self::tpl('fast-track-high', 'Fast-track high scorers', 'After an interview, if the AI score is 85+, advance the candidate and alert the team.', 'Pipeline', [
                ['condition.if', ['field' => 'ai_score', 'op' => 'greater', 'value' => '85']],
                ['recruitment.move_candidate', ['application_id' => '{{application_id}}', 'stage' => 'Final interview']],
                ['workspace.create_task', ['title' => 'Fast-track {{candidate_name}} — strong interview']],
            ]),
            self::tpl('interview-followup', 'Interview follow-up task', 'When an interview finishes, create a task to review it.', 'Productivity', [
                ['workspace.create_task', ['title' => 'Review interview: {{candidate_name}}']],
            ]),
            self::tpl('offer-accepted-onboarding', 'Offer accepted → onboarding', 'When an offer is accepted, kick off onboarding and notify the team.', 'Onboarding', [
                ['workspace.create_task', ['title' => 'Start onboarding for {{candidate_name}}']],
                ['users.notify', ['user_id' => '{{user_id}}', 'title' => 'Welcome aboard, {{candidate_name}}!']],
            ]),
            self::tpl('declined-to-pool', 'Offer declined → talent pool', 'When an offer is declined, keep the candidate in your talent pool.', 'Talent pool', [
                ['recruitment.add_to_talent_pool', ['user_id' => '{{user_id}}', 'pool' => 'Future opportunities']],
                ['util.audit', ['message' => '{{candidate_name}} declined — added to talent pool']],
            ]),
            self::tpl('rejected-to-pool', 'Rejected → nurture later', 'When a candidate is rejected, add them to a nurture pool for future roles.', 'Talent pool', [
                ['recruitment.add_to_talent_pool', ['user_id' => '{{user_id}}', 'pool' => 'Nurture']],
            ]),
            self::tpl('score-to-collection', 'Log scores to a collection', 'After an interview, compute a label and store the result as a record you can export.', 'Reporting', [
                ['logic.formula', ['name' => 'score_label', 'expression' => "ai_score >= 80 ? 'strong' : (ai_score >= 60 ? 'ok' : 'weak')"]],
                ['db.create_record', ['collection' => 'interview-scores', 'fields' => 'candidate: {{candidate_name}}, score: {{ai_score}}, label: {{score_label}}']],
            ]),
            self::tpl('payment-failed-alert', 'Alert on failed payment', 'When a payment attempt fails, notify and record it for follow-up.', 'Operations', [
                ['users.notify', ['user_id' => '{{user_id}}', 'title' => 'A payment attempt failed']],
                ['util.audit', ['message' => 'Payment failed: {{amount}} — {{reason}}']],
            ]),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $t) {
            if ($t['key'] === $key) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Build a left-to-right linear graph: a trigger then a chain of steps.
     *
     * @param  list<array{0:string,1:array<string,mixed>}>  $steps  [type, config] each
     * @return array{key:string,name:string,description:string,category:string,nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>}
     */
    private static function tpl(string $key, string $name, string $description, string $category, array $steps): array
    {
        // Each template's trigger is chosen by its first sensible event; map by key.
        $trigger = match (true) {
            str_starts_with($key, 'ai-screen'), str_starts_with($key, 'welcome') => 'trigger.candidate_applied',
            str_starts_with($key, 'auto-reject'), str_starts_with($key, 'fast-track'), str_starts_with($key, 'interview'), str_starts_with($key, 'score-') => 'trigger.interview_finished',
            str_starts_with($key, 'offer-accepted') => 'trigger.offer_accepted',
            str_starts_with($key, 'declined') => 'trigger.offer_declined',
            str_starts_with($key, 'rejected') => 'trigger.candidate_rejected',
            str_starts_with($key, 'payment') => 'trigger.payment_failed',
            default => 'trigger.manual',
        };

        $nodes = [['id' => 'n0', 'type' => $trigger, 'x' => 60, 'y' => 160, 'config' => []]];
        $edges = [];
        $prev = 'n0';
        $x = 60;
        foreach ($steps as $i => $step) {
            $x += 280;
            $id = 'n' . ($i + 1);
            $nodes[] = ['id' => $id, 'type' => $step[0], 'x' => $x, 'y' => 160, 'config' => $step[1] ?? []];
            $edges[] = ['from' => $prev, 'to' => $id, 'branch' => $step[0] === 'condition.if' ? '' : ''];
            $prev = $id;
        }

        return ['key' => $key, 'name' => $name, 'description' => $description, 'category' => $category, 'nodes' => $nodes, 'edges' => $edges];
    }
}
