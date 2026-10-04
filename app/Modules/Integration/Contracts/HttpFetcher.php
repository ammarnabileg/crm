<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Contracts;

use HaHireAI\Modules\Integration\Domain\HttpFetchResponse;

/**
 * Outbound HTTP GET transport for READING public data (social adapters). Kept
 * separate from {@see HttpClient} (which delivers webhooks) so adding read
 * capability never changes the existing transport or its test fakes. A fake
 * fetcher is injected in tests; a no-network environment simply returns
 * unreachable responses, never an exception.
 */
interface HttpFetcher
{
    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = [], int $timeoutSeconds = 8): HttpFetchResponse;
}
