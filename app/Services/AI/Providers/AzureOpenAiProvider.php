<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

/**
 * Azure OpenAI adapter (docs/51 §12). Same OpenAI request/response shape, but the
 * URL is the tenant's own resource + deployment and auth uses the `api-key` header
 * (not a bearer token). The resource endpoint, deployment and api-version come from
 * the tenant's stored credentials (the platform holds nothing).
 */
final class AzureOpenAiProvider extends OpenAiProvider
{
    public function key(): string
    {
        return 'azure_openai';
    }

    protected function endpoint(string $model, array $credentials): string
    {
        $base = rtrim((string) ($credentials['endpoint'] ?? $credentials['base_url'] ?? ''), '/');
        $deployment = (string) ($credentials['deployment'] ?? $model);
        $version = (string) ($credentials['api_version'] ?? '2024-02-15-preview');

        return $base . '/openai/deployments/' . rawurlencode($deployment) . '/chat/completions?api-version=' . rawurlencode($version);
    }

    protected function authHeaders(string $apiKey, array $credentials): array
    {
        return ['api-key: ' . $apiKey];
    }
}
