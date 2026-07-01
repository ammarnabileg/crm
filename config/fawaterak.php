<?php

declare(strict_types=1);

/*
 * Fawaterak top-up gateway (docs/WALLET_AND_BILLING.md §9). The platform is the
 * merchant selling wallet credits; these are PLATFORM-level secrets, read from the
 * environment (Constitution §5 — secrets never in the repo). When disabled / no
 * key, the gateway runs an offline "simulate" checkout so the platform works
 * without the live PSP (the billing analogue of the Manual gateway / AI Echo).
 */

return [
    'enabled' => (bool) env('FAWATERAK_ENABLED', false),
    'base_url' => (string) env('FAWATERAK_BASE_URL', 'https://app.fawaterk.com'),
    'token_url' => (string) env('FAWATERAK_TOKEN_URL', 'https://app.fawaterk.com/oauth/token'),
    // Bearer API key used to create invoices.
    'api_key' => (string) env('FAWATERAK_API_KEY', ''),
    // providerKey shown on the Fawaterak dashboard (identifies the merchant).
    'provider_key' => (string) env('FAWATERAK_PROVIDER_KEY', ''),
    // HASH API key used to verify inbound webhook signatures.
    'webhook_hash' => (string) env('FAWATERAK_WEBHOOK_HASH', ''),
    'currency' => (string) env('FAWATERAK_CURRENCY', 'USD'),
    'timeout' => (int) env('FAWATERAK_TIMEOUT', 15),
];
