<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Infrastructure;

use HaHireAI\Modules\Billing\Contracts\PaymentGateway;
use HaHireAI\Modules\Billing\Domain\PaymentResult;
use HaHireAI\Shared\Ulid;

/**
 * The built-in, network-free gateway (the billing analogue of the AI EchoProvider).
 * It settles charges manually/offline so the platform is fully functional without
 * an external PSP. Stripe/Moyasar adapters replace it by binding PaymentGateway
 * to their implementation (docs/BILLING_PLATFORM.md §4).
 */
final class ManualPaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function charge(int $amountCents, string $currency, string $description, array $metadata = []): PaymentResult
    {
        // Offline settlement: always succeeds, returning a traceable reference.
        return PaymentResult::ok('manual_' . strtolower(Ulid::generate()));
    }
}
