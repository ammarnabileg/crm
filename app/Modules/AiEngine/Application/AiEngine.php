<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\AiEngine\Application\Exceptions\AiException;
use HaHireAI\Modules\AiEngine\Domain\AiResponse;
use HaHireAI\Modules\AiEngine\Domain\AiResult;
use HaHireAI\Shared\Ulid;
use Throwable;

/**
 * The central AI Engine. Modules request a CAPABILITY (e.g. "summarize_candidate");
 * the engine picks the workspace's provider/model, renders the versioned prompt,
 * calls the provider with automatic fallback, records the session + usage/cost,
 * and returns an advisory result (humans decide). See docs/AI_ENGINE.md.
 */
final class AiEngine
{
    /** Illustrative price: 1 cent per 1,000 tokens. */
    private const CENTS_PER_1K_TOKENS = 1;

    public function __construct(
        private readonly Connection $connection,
        private readonly ProviderRegistry $providers,
        private readonly PromptEngine $prompts,
        private readonly AiSettingsService $settings,
    ) {
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function run(string $workspaceId, string $capability, array $variables = [], ?string $actorUserId = null): AiResult
    {
        $config = $this->settings->forWorkspace($workspaceId);
        $prompt = $this->prompts->render($capability, $variables);
        $startedAt = (float) hrtime(true);

        $providerKey = (string) $config['provider'];
        $fallbackFrom = null;

        try {
            $response = $this->call($workspaceId, $providerKey, $config, $prompt);
        } catch (Throwable $primaryError) {
            $fallback = $config['fallback_provider'];

            if (! is_string($fallback) || $fallback === '' || ! $this->providers->has($fallback)) {
                $this->recordFailure($workspaceId, $capability, $providerKey, $config, $prompt, $actorUserId, $primaryError);
                throw new AiException("AI capability [{$capability}] failed: " . $primaryError->getMessage(), previous: $primaryError);
            }

            $fallbackFrom = $providerKey;
            $providerKey = $fallback;
            $response = $this->call($workspaceId, $fallback, $config, $prompt);
        }

        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $costCents = $this->cost($response);

        $this->record($workspaceId, $capability, $providerKey, $response, $prompt, $latencyMs, $costCents, $fallbackFrom, $actorUserId, 'completed');

        return new AiResult(
            text: $response->text,
            provider: $providerKey,
            model: $response->model,
            inputTokens: $response->inputTokens,
            outputTokens: $response->outputTokens,
            costCents: $costCents,
            fallbackFrom: $fallbackFrom,
        );
    }

    /** @param array<string, mixed> $config */
    private function call(string $workspaceId, string $providerKey, array $config, string $prompt): AiResponse
    {
        $provider = $this->providers->get($providerKey);

        $apiKey = ($config['use_platform_key'] ?? true)
            ? (getenv(strtoupper($providerKey) . '_API_KEY') ?: null)
            : $this->settings->getKey($workspaceId, $providerKey);

        return $provider->complete($prompt, [
            'model' => $config['model'] ?? null,
            'api_key' => $apiKey,
        ]);
    }

    private function cost(AiResponse $response): int
    {
        return (int) ceil((($response->inputTokens + $response->outputTokens) / 1000) * self::CENTS_PER_1K_TOKENS);
    }

    /** @param array<string, mixed> $config */
    private function recordFailure(string $workspaceId, string $capability, string $providerKey, array $config, string $prompt, ?string $actorUserId, Throwable $error): void
    {
        $this->record(
            $workspaceId,
            $capability,
            $providerKey,
            new AiResponse('', 0, 0, $config['model'] ?? null),
            $prompt,
            0,
            0,
            null,
            $actorUserId,
            'failed',
            $error->getMessage(),
        );
    }

    private function record(string $workspaceId, string $capability, string $providerKey, AiResponse $response, string $prompt, int $latencyMs, int $costCents, ?string $fallbackFrom, ?string $actorUserId, string $status, ?string $error = null): void
    {
        $this->connection->statement(
            'INSERT INTO ai_sessions (id, workspace_id, capability, provider, model, status, prompt, response, input_tokens, output_tokens, cost_cents, latency_ms, fallback_from, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Ulid::generate(), $workspaceId, $capability, $providerKey, $response->model, $status,
                $prompt, $error !== null ? ('ERROR: ' . $error) : $response->text,
                $response->inputTokens, $response->outputTokens, $costCents, $latencyMs, $fallbackFrom, $actorUserId,
                gmdate('Y-m-d H:i:s'),
            ],
        );
    }

    /** @return array{sessions: int, tokens: int, cost_cents: int} */
    public function usageSummary(string $workspaceId): array
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS sessions, COALESCE(SUM(input_tokens + output_tokens),0) AS tokens, COALESCE(SUM(cost_cents),0) AS cost
               FROM ai_sessions WHERE workspace_id = ?',
            [$workspaceId],
        );

        return [
            'sessions' => (int) ($row['sessions'] ?? 0),
            'tokens' => (int) ($row['tokens'] ?? 0),
            'cost_cents' => (int) ($row['cost'] ?? 0),
        ];
    }
}
