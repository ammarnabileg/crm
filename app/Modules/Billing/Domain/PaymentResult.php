<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/** The outcome of a charge attempt. */
final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $reference = '',
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(string $reference): self
    {
        return new self(true, $reference);
    }

    public static function failed(string $error): self
    {
        return new self(false, '', $error);
    }
}
