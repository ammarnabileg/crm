<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/**
 * Pure proration arithmetic for mid-term plan changes (docs/WALLET_AND_BILLING.md
 * §14). A composed plan runs one monthly term; when a workspace upgrades or
 * downgrades before the term ends, only the *remaining* slice of the term is
 * settled now — the new full monthly price takes over at the next renewal.
 *
 * Deterministic and side-effect free: every input is an integer cent amount or a
 * Unix timestamp, so the same inputs always yield the same cents. Time is passed
 * in (never read from the clock) so callers and tests stay reproducible. All
 * money is integer cents; fractions are resolved with half-away-from-zero
 * rounding, matching PHP's default round().
 */
final class ProrationCalculator
{
    /**
     * Fraction of the term still remaining at $nowTs, clamped to [0.0, 1.0].
     * 1.0 before/at the start, 0.0 at/after the end, and the linear time share
     * in between. A zero-or-negative-length term yields 0.0 (nothing to prorate).
     */
    public function remainingFraction(int $nowTs, int $periodStartTs, int $periodEndTs): float
    {
        if ($periodEndTs <= $periodStartTs) {
            return 0.0;
        }
        if ($nowTs <= $periodStartTs) {
            return 1.0;
        }
        if ($nowTs >= $periodEndTs) {
            return 0.0;
        }

        return ($periodEndTs - $nowTs) / ($periodEndTs - $periodStartTs);
    }

    /**
     * The portion of a full-term amount owed for the remaining term, in cents.
     * Used to charge a mid-term addition (e.g. a seat) only for the days left.
     */
    public function prorate(int $fullCents, int $nowTs, int $periodStartTs, int $periodEndTs): int
    {
        return (int) round($fullCents * $this->remainingFraction($nowTs, $periodStartTs, $periodEndTs));
    }

    /**
     * Signed cents to settle a mid-term move from $oldMonthlyCents to
     * $newMonthlyCents, prorated to the remaining term:
     *   > 0  charge the wallet now  (upgrade — the buyer owes the difference),
     *   < 0  credit the wallet now  (downgrade — refund the unused difference),
     *   = 0  nothing to settle (same price, or no term remaining).
     */
    public function changeAmountCents(
        int $oldMonthlyCents,
        int $newMonthlyCents,
        int $nowTs,
        int $periodStartTs,
        int $periodEndTs,
    ): int {
        $fraction = $this->remainingFraction($nowTs, $periodStartTs, $periodEndTs);

        return (int) round(($newMonthlyCents - $oldMonthlyCents) * $fraction);
    }
}
