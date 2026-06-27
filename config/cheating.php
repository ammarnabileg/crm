<?php

declare(strict_types=1);

/**
 * Anti-Cheating signal catalog & banding (docs/51 §16, AI Interview Engine P8).
 *
 * CONFIDENCE ONLY, NEVER PROOF. Every entry here feeds an ADVISORY confidence
 * score — not an accusation and not a boolean "cheated". `weight` is each signal's
 * relative contribution (0..1-ish); App\Services\AntiCheat\CheatingDetector scales
 * the weighted sum of a single interview's signals into a normalized 0–100
 * confidence and derives a low/medium/high CONFIDENCE BAND from `bands`. The band
 * describes how much corroborating signal was seen — a human reviewer always
 * interprets it. Adding/tuning a signal is a config change, never a code change.
 */
return [
    // signal_type => relative weight + human-readable label/description.
    'signals' => [
        'tab_switch' => [
            'label'       => 'Tab Switch',
            'weight'      => 0.25,
            'description' => 'Candidate switched away from the interview tab.',
        ],
        'window_blur' => [
            'label'       => 'Window Lost Focus',
            'weight'      => 0.20,
            'description' => 'The interview window lost focus (possible app switch).',
        ],
        'paste_detected' => [
            'label'       => 'Paste Detected',
            'weight'      => 0.45,
            'description' => 'Content was pasted into an answer field.',
        ],
        'copy_detected' => [
            'label'       => 'Copy Detected',
            'weight'      => 0.30,
            'description' => 'Question or answer text was copied to the clipboard.',
        ],
        'multiple_faces' => [
            'label'       => 'Multiple Faces',
            'weight'      => 0.70,
            'description' => 'More than one face was visible in the camera frame.',
        ],
        'no_face_detected' => [
            'label'       => 'No Face Detected',
            'weight'      => 0.40,
            'description' => 'No face was visible in the camera frame.',
        ],
        'multiple_voices' => [
            'label'       => 'Multiple Voices',
            'weight'      => 0.65,
            'description' => 'More than one distinct voice was detected on the mic.',
        ],
        'external_audio' => [
            'label'       => 'External Audio',
            'weight'      => 0.35,
            'description' => 'Audio likely originating from another device was detected.',
        ],
        'rapid_answer' => [
            'label'       => 'Rapid Answer',
            'weight'      => 0.30,
            'description' => 'An answer was submitted implausibly fast for its length.',
        ],
        'ip_change' => [
            'label'       => 'IP Address Change',
            'weight'      => 0.50,
            'description' => 'The candidate connection changed IP mid-interview.',
        ],
        'devtools_opened' => [
            'label'       => 'Developer Tools Opened',
            'weight'      => 0.55,
            'description' => 'Browser developer tools were opened during the session.',
        ],
    ],

    // CONFIDENCE bands by normalized 0–100 score. These are bands, NOT verdicts:
    // confidence >= high → 'high', >= medium → 'medium', else 'low'.
    'bands' => [
        'high'   => 70,
        'medium' => 40,
    ],

    // Surface this wherever a confidence score is shown.
    'disclaimer' => 'This is an ADVISORY confidence score derived from behavioral '
        . 'signals. It is NOT proof of cheating, NOT an accusation, and NOT a final '
        . 'verdict. Signals can have innocent explanations; a human reviewer must '
        . 'interpret the score in context before any decision is made (docs/51 §16).',
];
