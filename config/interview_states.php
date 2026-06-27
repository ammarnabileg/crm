<?php

declare(strict_types=1);

/**
 * Interview State Machine rule sets (docs/51 §5, AI Interview Engine P1+).
 *
 * Per the Bible, every state carries Entry / Exit / Validation / Allowed-Actions /
 * Timeout / Recovery rules. They live here (config-driven, no-code editing) and are
 * merged onto the `interview_states` catalog by App\Services\Interview\StateMachine,
 * which enforces them. A tenant may override a state's rules via its
 * `interview_states.meta` row without a code change.
 *
 * Rule shape per state key:
 *   entry      => string[]  context flags required BEFORE entering
 *   exit       => string[]  context flags required BEFORE leaving
 *   validation => array     constraints checked while in the state
 *   actions    => string[]  actions permitted while in the state
 *   timeout    => array{minutes:int|null, on_timeout:string}  ('auto_advance'|'pause'|'fail'|'none')
 *   recovery   => array{on_disconnect:string, max_resumes:int} ('resume'|'restart_state'|'fail')
 */

$check = static fn (int $timeout): array => [
    'entry'      => [],
    'exit'       => ['check_passed'],
    'validation' => ['max_duration_minutes' => $timeout],
    'actions'    => ['run_check', 'retry', 'pause', 'skip'],
    'timeout'    => ['minutes' => $timeout, 'on_timeout' => 'pause'],
    'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 3],
];

$assessment = static fn (int $timeout): array => [
    'entry'      => ['identity_verified', 'devices_ready'],
    'exit'       => ['min_questions_answered'],
    'validation' => ['max_duration_minutes' => $timeout, 'min_questions' => 1],
    'actions'    => ['ask_question', 'generate_question', 'wait_answer', 'evaluate_answer', 'generate_follow_up', 'pause', 'skip'],
    'timeout'    => ['minutes' => $timeout, 'on_timeout' => 'auto_advance'],
    'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 3],
];

$conversation = static fn (int $timeout): array => [
    'entry'      => ['identity_verified'],
    'exit'       => [],
    'validation' => ['max_duration_minutes' => $timeout],
    'actions'    => ['ask_question', 'generate_question', 'wait_answer', 'evaluate_answer', 'generate_follow_up', 'pause', 'skip'],
    'timeout'    => ['minutes' => $timeout, 'on_timeout' => 'auto_advance'],
    'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 3],
];

$review = static fn (): array => [
    'entry'      => ['interview_completed'],
    'exit'       => ['decision_recorded'],
    'validation' => [],
    'actions'    => ['ai_analysis', 'score_candidate', 'generate_report', 'pause'],
    'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
    'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 5],
];

return [
    'rules' => [
        'draft' => [
            'entry'      => [],
            'exit'       => ['blueprint_assigned'],
            'validation' => [],
            'actions'    => ['edit', 'assign_blueprint', 'schedule', 'cancel'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 99],
        ],
        'scheduled' => [
            'entry'      => ['blueprint_assigned', 'scheduled_at'],
            'exit'       => [],
            'validation' => [],
            'actions'    => ['reschedule', 'notify_candidate', 'cancel', 'start'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 99],
        ],
        'waiting' => [
            'entry'      => [],
            'exit'       => [],
            'validation' => ['max_duration_minutes' => 60],
            'actions'    => ['resume', 'cancel', 'notify_candidate'],
            'timeout'    => ['minutes' => 60, 'on_timeout' => 'pause'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 99],
        ],
        'identity_verification' => $check(10),
        'device_check'          => $check(10),
        'environment_check'     => $check(10),
        'introduction'          => $conversation(15),
        'ice_breaking'          => $conversation(15),
        'cv_review'             => $conversation(20),
        'experience_discussion' => $conversation(30),
        'technical_assessment'  => $assessment(45),
        'behavioral_assessment' => $assessment(30),
        'scenario_questions'    => $assessment(30),
        'problem_solving'       => $assessment(45),
        'culture_fit'           => $assessment(20),
        'candidate_questions'   => $conversation(15),
        'final_evaluation'      => [
            'entry'      => ['interview_completed'],
            'exit'       => ['scores_aggregated'],
            'validation' => [],
            'actions'    => ['score_candidate', 'ai_analysis', 'pause'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 5],
        ],
        'ai_review'    => $review(),
        'human_review' => [
            'entry'      => ['decision_recorded'],
            'exit'       => ['human_approved'],
            'validation' => [],
            'actions'    => ['approve', 'edit_decision', 'reject', 'request_changes', 'add_note'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 99],
        ],
        'completed' => [
            'entry'      => ['human_approved'],
            'exit'       => [],
            'validation' => [],
            'actions'    => ['generate_report', 'notify_candidate', 'archive'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'resume', 'max_resumes' => 99],
        ],
        'archived' => [
            'entry'      => [],
            'exit'       => [],
            'validation' => [],
            'actions'    => ['view', 'export'],
            'timeout'    => ['minutes' => null, 'on_timeout' => 'none'],
            'recovery'   => ['on_disconnect' => 'none', 'max_resumes' => 0],
        ],
    ],
];
