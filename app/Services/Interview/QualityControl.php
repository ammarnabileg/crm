<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Services\AI\PromptGuard;

/**
 * AI Quality Control (docs/51 §14) — a pure, model-free gate applied to every model
 * OUTPUT before it is shown or persisted.
 *
 * It rejects empty output, output that exceeds the allowed length, output that was
 * required to be JSON but is not valid JSON, and output that echoes system/role
 * markers back at the user (a sign the model was successfully prompt-injected or is
 * leaking its instructions). The role/marker check reuses the same PromptGuard
 * signal engine that hardens inputs — if the output trips the `system_leak` or
 * `delimiter_injection` signals it is flagged.
 *
 * Deterministic and side-effect-free, so it is fully testable and never makes a
 * model call of its own.
 */
final class QualityControl
{
    public function __construct(private readonly ?PromptGuard $guard = null)
    {
    }

    /**
     * Check a model output against expectations.
     *
     * @param array{max_chars?:int, json?:bool} $expectations
     * @return array{ok:bool, issues:array<int,string>}
     */
    public function check(string $output, array $expectations = []): array
    {
        $issues = [];

        $trimmed = trim($output);
        if ($trimmed === '') {
            $issues[] = 'empty_output';
            // Nothing else is meaningful to check on empty output.
            return ['ok' => false, 'issues' => $issues];
        }

        $maxChars = (int) ($expectations['max_chars'] ?? 8000);
        if (mb_strlen($output) > $maxChars) {
            $issues[] = 'too_long';
        }

        if (! empty($expectations['json'])) {
            json_decode($output);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = 'invalid_json';
            }
        }

        $guard = $this->guard ?? new PromptGuard();
        $signals = $guard->inspect($output)['signals'];
        if (in_array('system_leak', $signals, true)) {
            $issues[] = 'system_leak';
        }
        if (in_array('delimiter_injection', $signals, true)) {
            $issues[] = 'delimiter_injection';
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }
}
