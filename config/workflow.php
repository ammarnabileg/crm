<?php

declare(strict_types=1);

/**
 * Workflow Builder catalog (docs/51 §8, AI Interview Engine P3).
 *
 * Node TYPES are a code-owned catalog (each maps to runtime behaviour), kept in
 * step with the `workflow_node_type` lookup seeded by migration 0035. `behaviors`
 * tells the WorkflowRuntime how to execute each type:
 *   - entry      : the single Start node; auto-advances along its one out-edge.
 *   - terminal   : Finish; ends the run.
 *   - branch     : If/Else; follows the first out-edge whose condition matches the
 *                  run context, else the default (null-condition) edge.
 *   - await      : pauses the run for external input — human approval, the
 *                  candidate's answer, or (until P4+) a model-produced result the
 *                  Orchestrator injects via WorkflowRuntime::resume().
 *
 * Phases 1–3 run with ZERO model calls: 'await' model nodes simply pause and are
 * resumed with injected output, so a workflow is fully testable end-to-end now.
 */
return [
    'max_steps' => 200, // runtime loop guard against cyclic graphs

    'node_types' => [
        'start', 'ask_question', 'generate_question', 'wait_answer', 'evaluate_answer',
        'ai_analysis', 'condition', 'generate_follow_up', 'score_candidate',
        'generate_report', 'human_approval', 'finish',
    ],

    'behaviors' => [
        'start'              => 'entry',
        'finish'             => 'terminal',
        'condition'          => 'branch',
        'human_approval'     => 'await',
        'wait_answer'        => 'await',
        'ask_question'       => 'await',
        'generate_question'  => 'await',
        'evaluate_answer'    => 'await',
        'ai_analysis'        => 'await',
        'generate_follow_up' => 'await',
        'score_candidate'    => 'await',
        'generate_report'    => 'await',
    ],
];
