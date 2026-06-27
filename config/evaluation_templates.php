<?php

declare(strict_types=1);

/**
 * Evaluation Template starter library (docs/51 §9, AI Interview Engine P2).
 *
 * Role-family scoring rubrics a tenant can INSTANTIATE into its own
 * `evaluation_forms` + `evaluation_form_fields` (App\Services\Evaluation\
 * EvaluationTemplate::instantiateFromLibrary). These are seeds, not hard-coded
 * behaviour — once instantiated they are ordinary tenant data: editable,
 * versionable and feature-flaggable (docs/51 "Configuration-driven"). Adding a
 * role rubric here is a data change, never a code change.
 *
 * Each criterion: key (stable id), label, weight (relative; the Decision Engine
 * normalizes by the sum of weights, so any scale works), max (per-criterion
 * ceiling), optional min. `pass_threshold` is the normalized 0–100 cut-off.
 */
return [
    'field_type' => 'rating', // lookup_values key under `evaluation_field_type`
    'max_per_criterion' => 5.0,

    'library' => [
        'backend_engineer' => [
            'name'           => 'Backend Engineer',
            'description'    => 'Default rubric for backend engineering interviews.',
            'pass_threshold' => 70.0,
            'criteria'       => [
                ['key' => 'technical_depth', 'label' => 'Technical Depth',     'weight' => 30, 'max' => 5],
                ['key' => 'problem_solving', 'label' => 'Problem Solving',      'weight' => 25, 'max' => 5],
                ['key' => 'system_design',   'label' => 'System Design',        'weight' => 20, 'max' => 5],
                ['key' => 'communication',   'label' => 'Communication',        'weight' => 15, 'max' => 5],
                ['key' => 'culture_fit',     'label' => 'Culture Fit',          'weight' => 10, 'max' => 5],
            ],
        ],

        'sales_representative' => [
            'name'           => 'Sales Representative',
            'description'    => 'Default rubric for sales interviews.',
            'pass_threshold' => 65.0,
            'criteria'       => [
                ['key' => 'communication',     'label' => 'Communication',       'weight' => 25, 'max' => 5],
                ['key' => 'persuasion',        'label' => 'Persuasion & Closing', 'weight' => 25, 'max' => 5],
                ['key' => 'product_knowledge', 'label' => 'Product Knowledge',   'weight' => 20, 'max' => 5],
                ['key' => 'resilience',        'label' => 'Resilience',          'weight' => 15, 'max' => 5],
                ['key' => 'culture_fit',       'label' => 'Culture Fit',         'weight' => 15, 'max' => 5],
            ],
        ],

        'customer_support' => [
            'name'           => 'Customer Support',
            'description'    => 'Default rubric for customer support interviews.',
            'pass_threshold' => 65.0,
            'criteria'       => [
                ['key' => 'empathy',           'label' => 'Empathy',             'weight' => 25, 'max' => 5],
                ['key' => 'communication',     'label' => 'Communication',       'weight' => 25, 'max' => 5],
                ['key' => 'problem_solving',   'label' => 'Problem Solving',     'weight' => 20, 'max' => 5],
                ['key' => 'product_knowledge', 'label' => 'Product Knowledge',   'weight' => 15, 'max' => 5],
                ['key' => 'patience',          'label' => 'Patience',            'weight' => 15, 'max' => 5],
            ],
        ],
    ],
];
