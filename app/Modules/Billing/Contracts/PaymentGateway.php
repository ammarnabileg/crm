<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Contracts;

use HaHireAI\Modules\Billing\Domain\PaymentResult;

/**
 * A payment provider. The platform charges through this contract and never talks
 * to a provider SDK directly — Stripe/Moyasar are adapters behind it, exactly as
 * AI providers sit behind the AI Engine (docs/BILLING_PLATFORM.md §4).
 */
interface PaymentGateway
{
    public function key(): string;

    /**
     * Collect a payment. Implementations MUST NOT throw on a declined charge —
     * return an unsuccessful PaymentResult instead.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function charge(int $amountCents, string $currency, string $description, array $metadata = []): PaymentResult;
}
