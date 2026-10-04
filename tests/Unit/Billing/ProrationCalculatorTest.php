<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Billing;

use HaHireAI\Modules\Billing\Domain\ProrationCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure proration arithmetic (docs/WALLET_AND_BILLING.md §14). No database, no
 * clock: deterministic cents in, deterministic cents out.
 */
final class ProrationCalculatorTest extends TestCase
{
    private ProrationCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new ProrationCalculator();
    }

    public function test_remaining_fraction_is_full_at_or_before_start(): void
    {
        $this->assertSame(1.0, $this->calc->remainingFraction(0, 0, 100));
        $this->assertSame(1.0, $this->calc->remainingFraction(-50, 0, 100));
    }

    public function test_remaining_fraction_is_zero_at_or_after_end(): void
    {
        $this->assertSame(0.0, $this->calc->remainingFraction(100, 0, 100));
        $this->assertSame(0.0, $this->calc->remainingFraction(200, 0, 100));
    }

    public function test_remaining_fraction_is_zero_for_a_degenerate_term(): void
    {
        $this->assertSame(0.0, $this->calc->remainingFraction(5, 10, 10));
        $this->assertSame(0.0, $this->calc->remainingFraction(5, 10, 0));
    }

    public function test_remaining_fraction_is_the_linear_time_share(): void
    {
        $this->assertSame(0.5, $this->calc->remainingFraction(50, 0, 100));
        $this->assertSame(0.25, $this->calc->remainingFraction(75, 0, 100));
    }

    public function test_prorate_full_term_is_the_full_amount(): void
    {
        $this->assertSame(3100, $this->calc->prorate(3100, 0, 0, 100));
    }

    public function test_prorate_half_term_halves_the_amount_and_rounds_half_up(): void
    {
        $this->assertSame(500, $this->calc->prorate(1000, 50, 0, 100));
        // 999 * 0.5 = 499.5 -> 500 (half away from zero).
        $this->assertSame(500, $this->calc->prorate(999, 50, 0, 100));
    }

    public function test_prorate_after_end_is_zero(): void
    {
        $this->assertSame(0, $this->calc->prorate(3100, 100, 0, 100));
    }

    public function test_change_amount_is_a_positive_charge_on_upgrade(): void
    {
        // Mid-term (half remaining) upgrade 900 -> 2900 monthly => +1000 now.
        $this->assertSame(1000, $this->calc->changeAmountCents(900, 2900, 50, 0, 100));
    }

    public function test_change_amount_is_a_negative_credit_on_downgrade(): void
    {
        // Mid-term (half remaining) downgrade 2900 -> 900 monthly => -1000 now.
        $this->assertSame(-1000, $this->calc->changeAmountCents(2900, 900, 50, 0, 100));
    }

    public function test_change_amount_is_zero_when_price_is_unchanged(): void
    {
        $this->assertSame(0, $this->calc->changeAmountCents(2900, 2900, 50, 0, 100));
    }

    public function test_change_amount_is_zero_after_the_term_even_for_a_big_jump(): void
    {
        $this->assertSame(0, $this->calc->changeAmountCents(900, 9900, 100, 0, 100));
    }

    public function test_real_calendar_month_proration_is_deterministic(): void
    {
        $start = (int) strtotime('2026-03-01 00:00:00 UTC'); // 31-day term
        $end = (int) strtotime('2026-04-01 00:00:00 UTC');
        $now = (int) strtotime('2026-03-16 00:00:00 UTC');   // 15 days elapsed, 16 left

        // 3100 * 16/31 = 1600.0 exactly.
        $this->assertSame(1600, $this->calc->prorate(3100, $now, $start, $end));
        // Upgrade 900 -> 4000 monthly, 16/31 remaining => round(3100 * 16/31) = 1600.
        $this->assertSame(1600, $this->calc->changeAmountCents(900, 4000, $now, $start, $end));
    }
}
