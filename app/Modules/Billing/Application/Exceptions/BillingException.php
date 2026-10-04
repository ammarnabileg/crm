<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application\Exceptions;

use RuntimeException;

/** A recoverable billing-domain error (unknown plan, nothing to cancel, …). */
final class BillingException extends RuntimeException
{
}
