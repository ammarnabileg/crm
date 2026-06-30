<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Domain\HttpFetchResponse;

/**
 * cURL-backed GET transport for the social adapters. Always returns a response
 * (never throws); honours a standard outbound proxy so sandboxed/proxied hosts
 * can reach public APIs, and caps time so a slow source can never stall the
 * apply flow. Follows redirects and sends a polite User-Agent.
 */
final class CurlHttpFetcher implements HttpFetcher
{
    public function get(string $url, array $headers = [], int $timeoutSeconds = 8): HttpFetchResponse
    {
        if (! function_exists('curl_init') || ! preg_match('#^https?://#i', $url)) {
            return HttpFetchResponse::failed('curl unavailable or invalid url');
        }

        $ch = curl_init();
        $headerLines = ['User-Agent: HaHireAI-FirstImpression/1.0', 'Accept: application/json, text/html;q=0.8'];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => max(2, min(20, $timeoutSeconds)),
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
        if (is_string($proxy) && $proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            if (is_file('/root/.ccr/ca-bundle.crt')) {
                curl_setopt($ch, CURLOPT_CAINFO, '/root/.ccr/ca-bundle.crt');
            }
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code === 0) {
            return HttpFetchResponse::failed($err !== '' ? $err : 'request failed');
        }

        // Cap body size we keep in memory (public profiles are small).
        return new HttpFetchResponse($code, mb_substr((string) $body, 0, 200_000));
    }
}
