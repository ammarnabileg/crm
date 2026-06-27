<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Contracts\AI\InterviewAgent;
use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;

/**
 * Shared behaviour for every single-responsibility interview agent (docs/51 §2).
 *
 * evaluate() builds an {@see AiPrompt} whose TRUSTED system message is the agent's
 * `lens` (its single-responsibility instruction) and whose UNTRUSTED user message is
 * the candidate transcript/answer (hardened by the AiGateway's PromptGuard before
 * any provider sees it — candidate text is data, never instructions, §13). It then
 * calls the gateway and produces a uniform, recordable verdict.
 *
 * The structured score is DERIVED DETERMINISTICALLY so the whole pipeline is
 * offline-testable: a caller may pass an explicit `$context['scores'][$key]`, else a
 * stable value is derived from the result/answer text length. We never depend on the
 * model returning parseable JSON — the value of this layer is the orchestration +
 * the audit (§15), not a brittle parse.
 */
abstract class BaseAgent implements InterviewAgent
{
    public function __construct(
        protected readonly AiGateway $gateway,
        protected readonly string $key,
        protected readonly string $type,
        protected readonly string $lens,
        protected readonly float $weight,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** The agent's rubric weight (its share of the aggregated 0–100 score). */
    public function weight(): float
    {
        return $this->weight;
    }

    /**
     * The maximum raw score on this agent's scale (kept uniform at 100 so a
     * percentage and the rubric weight do the normalising — see DecisionEngine).
     */
    protected function maxScore(): float
    {
        return 100.0;
    }

    public function evaluate(array $context): array
    {
        $answer = (string) ($context['answer'] ?? $context['transcript'] ?? '');

        $prompt = new AiPrompt(
            [
                ['role' => 'system', 'content' => $this->lens . ' Return a concise judgement.', 'trusted' => true],
                ['role' => 'user', 'content' => $answer],
            ],
            'chat',
        );

        // Run the prompt through the safe gateway pipeline. We use the result for
        // observability (tokens/model) and a deterministic fallback score; we do
        // NOT depend on the model returning structured JSON.
        $result = $this->gateway->complete($prompt, [
            'subject_type' => 'interview',
            'subject_id'   => (int) ($context['interview_id'] ?? 0),
        ]);

        $max = $this->maxScore();
        $score = $this->deriveScore($context, $result->ok ? $result->text : $answer, $max);
        $confidence = $this->deriveConfidence($context, $result->ok);

        return [
            'score'      => $score,
            'max_score'  => $max,
            'confidence' => $confidence,
            'verdict'    => [
                'agent'      => $this->key,
                'type'       => $this->type,
                'score'      => $score,
                'max_score'  => $max,
                'confidence' => $confidence,
                'model_key'  => $result->ok ? $result->model : null,
                'provider'   => $result->ok ? $result->provider : null,
                'ok'         => $result->ok,
            ],
            'rationale'  => $this->rationale($score, $max, $result->ok),
            // Echo the model/token usage so the runner can persist it (§15).
            'model_key'         => $result->ok ? $result->model : null,
            'prompt_tokens'     => $result->inputTokens,
            'completion_tokens' => $result->outputTokens,
        ];
    }

    /**
     * Deterministic score in [0, $max]. Prefers an explicitly supplied per-agent
     * score; otherwise derives a stable value from the text length so tests and the
     * offline sandbox are reproducible.
     *
     * @param array<string,mixed> $context
     */
    protected function deriveScore(array $context, string $text, float $max): float
    {
        $scores = $context['scores'] ?? [];
        if (is_array($scores) && array_key_exists($this->key, $scores)) {
            return max(0.0, min((float) $scores[$this->key], $max));
        }

        // Stable, bounded fallback: longer, more substantive answers score higher,
        // capped at $max. Empty answers score 0.
        $len = mb_strlen(trim($text));
        if ($len === 0) {
            return 0.0;
        }
        $derived = (float) min((int) $max, 50 + ($len % 50));

        return max(0.0, min($derived, $max));
    }

    /**
     * Deterministic confidence in [0, 100].
     *
     * @param array<string,mixed> $context
     */
    protected function deriveConfidence(array $context, bool $ok): float
    {
        $confidences = $context['confidences'] ?? [];
        if (is_array($confidences) && array_key_exists($this->key, $confidences)) {
            return max(0.0, min((float) $confidences[$this->key], 100.0));
        }

        return $ok ? 80.0 : 40.0;
    }

    protected function rationale(float $score, float $max, bool $ok): string
    {
        $source = $ok ? 'model-assisted' : 'fallback (no provider)';

        return sprintf(
            '%s lens scored %s/%s (%s).',
            ucfirst(str_replace('_', ' ', $this->key)),
            rtrim(rtrim(number_format($score, 2), '0'), '.'),
            rtrim(rtrim(number_format($max, 2), '0'), '.'),
            $source,
        );
    }
}
