<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Domain;

/**
 * Turns a payment-gateway failure code into a precise, human explanation and a
 * concrete remedy, so the System Owner's charge report says exactly what went
 * wrong and how to fix it (docs/BILLING_PLATFORM.md).
 */
final class PaymentDiagnostics
{
    /** @var array<string, array{cause: string, remedy: string}> */
    private const MAP = [
        'card_declined' => [
            'cause' => 'The issuing bank declined the charge (the most common decline; the bank gives no further reason for security).',
            'remedy' => 'Ask the member to try a different card, or to call their bank to authorise the payment, then retry.',
        ],
        'insufficient_funds' => [
            'cause' => 'The card does not have enough available balance for this charge.',
            'remedy' => 'Ask the member to use a card with sufficient funds or to top up, then retry the charge.',
        ],
        'expired_card' => [
            'cause' => 'The card has passed its expiry date.',
            'remedy' => 'Ask the member to update their card with a valid expiry date (or add a new card) and retry.',
        ],
        'incorrect_cvc' => [
            'cause' => 'The card security code (CVC/CVV) was rejected.',
            'remedy' => 'Ask the member to re-enter the correct 3- or 4-digit security code and retry.',
        ],
        'incorrect_number' => [
            'cause' => 'The card number is invalid or was mistyped.',
            'remedy' => 'Ask the member to re-enter a valid card number and retry.',
        ],
        'processing_error' => [
            'cause' => 'A temporary error occurred at the bank or gateway while processing the charge.',
            'remedy' => 'Wait a few minutes and retry. If it persists, ask the member to use a different card.',
        ],
        'authentication_required' => [
            'cause' => 'The bank requires 3-D Secure authentication (Strong Customer Authentication) for this charge.',
            'remedy' => 'Ask the member to complete the bank verification (3-D Secure) prompt to approve the payment.',
        ],
        'currency_not_supported' => [
            'cause' => 'The card or account cannot be charged in the plan currency.',
            'remedy' => 'Use a card that supports the plan currency, or set the plan to a currency the member can pay in.',
        ],
        'rate_limit' => [
            'cause' => 'Too many requests were sent to the payment gateway in a short time.',
            'remedy' => 'Wait briefly and retry. If frequent, reduce retry frequency or contact the gateway.',
        ],
        'api_connection_error' => [
            'cause' => 'The platform could not reach the payment gateway (network or gateway outage).',
            'remedy' => 'Check network connectivity and the gateway status page, then retry. No charge was made.',
        ],
        'authentication_error' => [
            'cause' => 'The gateway rejected the platform’s API credentials (invalid or revoked secret key).',
            'remedy' => 'Verify and update the payment gateway secret key in platform settings, then retry.',
        ],
        'no_gateway' => [
            'cause' => 'No real payment gateway is connected — the platform is running in free/offline mode.',
            'remedy' => 'Connect a payment provider (e.g. Stripe/Moyasar), or keep payments switched off to run free.',
        ],
    ];

    /**
     * @return array{code: string, cause: string, remedy: string}
     */
    public static function explain(?string $code): array
    {
        $code = $code !== null && $code !== '' ? $code : 'unknown';
        $entry = self::MAP[$code] ?? [
            'cause' => 'The gateway returned an error that isn’t in our known list.',
            'remedy' => 'Open the charge in the payment gateway’s dashboard for the full reason, or contact the gateway’s support with the reference.',
        ];

        return ['code' => $code, 'cause' => $entry['cause'], 'remedy' => $entry['remedy']];
    }
}
