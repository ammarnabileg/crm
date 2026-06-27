<?php

declare(strict_types=1);

namespace App\Services\AntiCheat;

use App\Models\CheatingScore;
use App\Models\CheatingSignal;
use InvalidArgumentException;

/**
 * Anti-Cheating Engine (docs/51 §16, AI Interview Engine P8).
 *
 * CONFIDENCE ONLY, NEVER PROOF. This service records ADVISORY behavioral signals
 * and aggregates them into a normalized 0–100 CONFIDENCE score with a low/medium/
 * high CONFIDENCE BAND. It deliberately exposes NO method that returns a boolean
 * "cheated" and produces NO verdict or accusation — the score is a hint for a human
 * reviewer, who always interprets it in context. Signals can have innocent
 * explanations.
 *
 * Signal weights come from config/cheating.php (config-driven; tuning a weight is a
 * config change, not a code change). recordSignal() appends an immutable signal;
 * computeConfidence() folds an interview's signals into one upserted CheatingScore.
 */
final class CheatingDetector
{
    /**
     * Record one ADVISORY signal for an interview. The weight is resolved from
     * config/cheating.php; an unknown signal type is rejected so the catalog stays
     * the single source of truth. Tenant + uuid are stamped by the Model layer.
     *
     * @param array<string,mixed> $meta optional structured context for the signal.
     *
     * @throws InvalidArgumentException when $type is not in the configured catalog.
     */
    public function recordSignal(int $interviewId, string $type, ?float $value = null, array $meta = []): CheatingSignal
    {
        $catalog = $this->catalog();
        if (! isset($catalog[$type])) {
            throw new InvalidArgumentException("Unknown cheating signal type [{$type}].");
        }

        $weight = (float) ($catalog[$type]['weight'] ?? 0.0);

        return CheatingSignal::create([
            'interview_id' => $interviewId,
            'signal_type'  => $type,
            'weight'       => $weight,
            'value'        => $value,
            'observed_at'  => now(),
            'meta'         => $meta === []
                ? null
                : json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at'   => now(),
        ]);
    }

    /**
     * Aggregate an interview's signals into a normalized 0–100 CONFIDENCE score and
     * a low/medium/high CONFIDENCE BAND, then upsert the single cheating_scores row
     * for that interview (UNIQUE interview_id).
     *
     * The raw contribution of a signal type is weight × occurrences. Contributions
     * are summed, scaled to a 0–100 confidence and capped at 100. `breakdown` keeps
     * the per-signal-type contribution so the score is explainable. This is a
     * CONFIDENCE, not proof: `level` is a band describing how much corroborating
     * signal was seen, never a finding of misconduct.
     */
    public function computeConfidence(int $interviewId): CheatingScore
    {
        $signals = CheatingSignal::where('interview_id', '=', $interviewId);

        $contributions = [];   // signal_type => summed weight*occurrence
        $rawTotal = 0.0;
        $count = 0;

        foreach ($signals as $signal) {
            $type = (string) $signal->signal_type;
            $weight = (float) $signal->weight;

            $contributions[$type] = round(($contributions[$type] ?? 0.0) + $weight, 3);
            $rawTotal += $weight;
            $count++;
        }

        // Scale the weighted sum onto 0–100 and cap. Weights are ~0..1 each, so the
        // SCALE controls how many "full-weight" signals saturate the confidence.
        $confidence = round(min(100.0, $rawTotal * self::CONFIDENCE_SCALE), 2);
        $level = $this->bandFor($confidence);

        $breakdown = [];
        foreach ($contributions as $type => $raw) {
            $breakdown[$type] = round(min(100.0, $raw * self::CONFIDENCE_SCALE), 2);
        }

        $payload = [
            'confidence'    => $confidence,
            'level'         => $level,
            'signals_count' => $count,
            'breakdown'     => json_encode($breakdown, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'computed_at'   => now(),
        ];

        $existing = CheatingScore::findBy('interview_id', $interviewId);
        if ($existing !== null) {
            $existing->update($payload);

            return CheatingScore::findOrFail((int) $existing->getKey());
        }

        return CheatingScore::create(['interview_id' => $interviewId] + $payload);
    }

    /**
     * The advisory-not-proof statement. Surface this anywhere a confidence score is
     * displayed (docs/51 §16).
     */
    public function disclaimer(): string
    {
        return (string) config(
            'cheating.disclaimer',
            'This is an advisory confidence score, not proof of cheating.'
        );
    }

    /** How aggressively the weighted signal sum maps onto the 0–100 confidence. */
    private const CONFIDENCE_SCALE = 25.0;

    /**
     * Derive the low/medium/high CONFIDENCE BAND from a 0–100 confidence using the
     * configured thresholds. This is a band, NOT a verdict.
     */
    private function bandFor(float $confidence): string
    {
        $high = (float) config('cheating.bands.high', 70);
        $medium = (float) config('cheating.bands.medium', 40);

        if ($confidence >= $high) {
            return 'high';
        }

        if ($confidence >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @return array<string, array<string,mixed>> the configured signal catalog.
     */
    private function catalog(): array
    {
        return (array) config('cheating.signals', []);
    }
}
