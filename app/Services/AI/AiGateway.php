<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\AiProvider;
use App\Core\Database;
use App\Models\AiCredential;
use App\Services\AI\Providers\FakeProvider;

/**
 * AI Gateway (docs/51 §12–13) — the single safe entry point for every model call.
 *
 * Pipeline per request: (1) PromptGuard hardens every UNTRUSTED message and blocks
 * outright injection; (2) TokenOptimizer trims the payload; (3) ModelRouter picks an
 * ordered candidate list from the tenant's own keys; (4) the gateway tries each
 * provider in turn, falling back on failure, and records the request + response for
 * observability/audit (all model I/O is audited, §Security). Providers are resolved
 * from a registry, so real adapters (OpenAI/Claude/…) and the offline sandbox plug
 * in without touching this orchestration. The platform never holds a key —
 * credentials are decrypted per-call from `tenant_ai_keys`.
 */
final class AiGateway
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function __construct(
        private readonly Database $db,
        private readonly PromptGuard $guard,
        private readonly TokenOptimizer $optimizer,
        private readonly ModelRouter $router,
    ) {
    }

    public static function make(): self
    {
        $gateway = new self(app('db'), new PromptGuard(), new TokenOptimizer(), ModelRouter::make());
        $gateway->register(new FakeProvider('sandbox')); // offline sandbox is always available

        return $gateway;
    }

    public function register(AiProvider $provider): self
    {
        $this->providers[$provider->key()] = $provider;

        return $this;
    }

    /**
     * Run a prompt through the full safe pipeline for the current tenant.
     *
     * @param array{prefer?:string, candidates?:array<int,array<string,mixed>>,
     *              subject_type?:string, subject_id?:int, user_id?:int} $context
     */
    public function complete(AiPrompt $prompt, array $context = []): AiResult
    {
        // 1) MANDATORY prompt-injection guard on every untrusted message.
        [$messages, $risk, $blocked, $signals] = $this->guardMessages($prompt->messages);
        if ($blocked) {
            return AiResult::failure('blocked_by_prompt_guard:risk=' . $risk);
        }

        // 2) Token optimization.
        $messages = $this->optimizer->optimize($messages);
        $prompt = $prompt->withMessages($messages);

        // 3) Route to the tenant's providers (or explicit candidates for testing).
        $candidates = $context['candidates']
            ?? $this->router->candidatesFor($prompt->capability, $context['prefer'] ?? null);
        if ($candidates === []) {
            return AiResult::failure('no_provider_configured');
        }

        // 4) Try in order; fall back on failure; audit each attempt.
        $lastError = 'no_runnable_provider';
        foreach ($candidates as $cand) {
            $provider = $this->providers[$cand['provider'] ?? ''] ?? null;
            if ($provider === null) {
                $lastError = 'no_adapter:' . ($cand['provider'] ?? '?');
                continue;
            }

            $credentials = $this->credentialsFor((int) ($cand['key_id'] ?? 0));
            $call = $prompt->withOptions(['model' => $cand['model'] ?? null]);
            $result = $provider->complete($call, $credentials, []);
            $this->record($prompt, $cand, $result, $risk, $signals, $context);

            if ($result->ok) {
                return $result;
            }
            $lastError = $result->error ?? 'provider_failed';
        }

        return AiResult::failure('all_providers_failed:' . $lastError);
    }

    /**
     * Harden untrusted messages; system/trusted messages pass through unchanged.
     *
     * @param array<int, array<string,mixed>> $messages
     * @return array{0:array<int,array<string,mixed>>,1:int,2:bool,3:array<int,string>}
     */
    private function guardMessages(array $messages): array
    {
        $out = [];
        $maxRisk = 0;
        $blocked = false;
        $signals = [];

        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $trusted = ! empty($m['trusted']) || $role === 'system' || $role === 'assistant';
            if ($trusted) {
                $out[] = $m;
                continue;
            }

            $inspection = $this->guard->inspect((string) ($m['content'] ?? ''));
            $maxRisk = max($maxRisk, $inspection['risk']);
            $signals = array_merge($signals, $inspection['signals']);
            if ($inspection['blocked']) {
                $blocked = true;
            }
            $m['content'] = $inspection['sanitized'];
            $out[] = $m;
        }

        return [$out, $maxRisk, $blocked, array_values(array_unique($signals))];
    }

    /** @return array<string,mixed> decrypted secrets for a tenant key (empty if none). */
    private function credentialsFor(int $keyId): array
    {
        if ($keyId === 0) {
            return [];
        }
        $credential = AiCredential::find($keyId);

        return $credential !== null ? $credential->secrets() : [];
    }

    /**
     * Audit one provider attempt: write ai_requests + ai_responses (FK-light,
     * partition-ready — Bible §7). No candidate PII beyond the prompt the tenant
     * itself composed; credentials are never logged.
     *
     * @param array<string,mixed> $cand
     * @param array<int,string> $signals
     * @param array<string,mixed> $context
     */
    private function record(AiPrompt $prompt, array $cand, AiResult $result, int $risk, array $signals, array $context): void
    {
        $now = date('Y-m-d H:i:s');
        $workspaceId = tenant()->id();
        $providerId = $this->providerId((string) ($cand['provider'] ?? ''));

        $requestId = $this->db->table('ai_requests')->insertGetId([
            'workspace_id'     => $workspaceId,
            'tenant_ai_key_id' => $cand['key_id'] ?? null,
            'provider_id'      => $providerId,
            'model_id'         => $cand['model_id'] ?? null,
            'model_key'        => $cand['model'] ?? null,
            'capability'       => $prompt->capability,
            'subject_type'     => $context['subject_type'] ?? null,
            'subject_id'       => $context['subject_id'] ?? null,
            'user_id'          => $context['user_id'] ?? null,
            'status'           => $result->ok ? 'completed' : 'failed',
            'prompt_tokens'    => $result->inputTokens,
            'request_hash'     => hash('sha256', $prompt->text()),
            'created_at'       => $now,
        ]);

        $this->db->table('ai_responses')->insert([
            'request_id'        => $requestId,
            'workspace_id'      => $workspaceId,
            'model_id'          => $cand['model_id'] ?? null,
            'finish_reason'     => $result->ok ? 'stop' : 'error',
            'prompt_tokens'     => $result->inputTokens,
            'completion_tokens' => $result->outputTokens,
            'total_tokens'      => $result->inputTokens + $result->outputTokens,
            'content'           => $result->ok ? $result->text : (string) $result->error,
            'usage_raw'         => json_encode(['guard_risk' => $risk, 'guard_signals' => $signals], JSON_UNESCAPED_SLASHES),
            'created_at'        => $now,
        ]);
    }

    private function providerId(string $providerKey): ?int
    {
        if ($providerKey === '') {
            return null;
        }
        $id = $this->db->table('ai_providers')->where('key', '=', $providerKey)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
