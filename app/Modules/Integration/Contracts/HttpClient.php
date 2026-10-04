<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Contracts;

use HaHireAI\Modules\Integration\Domain\HttpClientResponse;

/**
 * Outbound HTTP transport. Abstracted so webhook delivery is testable without
 * real network calls (a fake client is injected in tests). See
 * docs/INTEGRATION_PLATFORM.md §5.
 */
interface HttpClient
{
    /**
     * @param  array<string, string>  $headers
     */
    public function post(string $url, string $body, array $headers = [], int $timeoutSeconds = 10): HttpClientResponse;
}
