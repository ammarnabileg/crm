<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Core\Database;
use App\Models\AgentRun;
use App\Models\AiAgent;
use App\Services\AI\AiGateway;
use App\Services\Evaluation\DecisionEngine;

/**
 * Multi-Agent orchestrator (docs/51 §2, §3, §15).
 *
 * run() resolves the effective agent catalog (system rows + the current tenant's
 * overrides, merged like the StateMachine merges interview_states), instantiates one
 * generic {@see Agent} per entry over a SHARED AiGateway, evaluates each against the
 * interview context, and RECORDS one immutable `agent_runs` row per agent (§15 —
 * Explainable AI audit). It returns the per-agent verdicts keyed by agent key.
 *
 * aggregate() turns the scoring agents' weighted verdicts into one explainable
 * decision by REUSING {@see DecisionEngine::score()}: each scoring agent becomes a
 * rubric criterion [key, weight, max] and its score a raw score, so the engine
 * yields a normalised 0–100, a pass/fail and a per-agent factor breakdown — the same
 * deterministic, auditable path human scorecards use. The 'decision' agent itself
 * does not score; it names the aggregation step.
 *
 * The AiGateway is injected so tests can register a FakeProvider; make() wires the
 * default offline-safe gateway.
 */
final class AgentRunner
{
    public function __construct(
        private readonly Database $db,
        private readonly AiGateway $gateway,
    ) {
    }

    public static function make(): self
    {
        return new self(app('db'), AiGateway::make());
    }

    /**
     * Resolve, instantiate and run the active agents for an interview, recording one
     * `agent_runs` row each.
     *
     * @param array<string,mixed>  $context   interview signals (answer/transcript,
     *                                         optional pre-computed scores).
     * @param array<int,string>    $agentKeys restrict to these keys (default: all).
     * @return array<string, array<string,mixed>> verdicts keyed by agent key.
     */
    public function run(int $interviewId, array $context, array $agentKeys = []): array
    {
        $context['interview_id'] ??= $interviewId;

        $verdicts = [];
        foreach ($this->resolveAgents($agentKeys) as $key => $descriptor) {
            $agent = Agent::fromDescriptor($this->gateway, $descriptor);
            $verdict = $agent->evaluate($context);
            $verdict['weight'] = (float) ($descriptor['weight'] ?? 0);
            $verdict['agent_type'] = $agent->type();

            $this->record($interviewId, $key, $verdict);
            $verdicts[$key] = $verdict;
        }

        return $verdicts;
    }

    /**
     * Aggregate the SCORING agents' weighted verdicts into one explainable decision
     * by reusing the DecisionEngine. The 'decision' (aggregator) agent is excluded
     * from the rubric — it does not score.
     *
     * @param array<string, array<string,mixed>> $verdicts as returned by run().
     * @return array{overall_score:float, max_score:float, normalized_score:float,
     *               pass_threshold:float, passed:bool, recommendation_key:string,
     *               scored_count:int, factors:array<int, array<string,mixed>>}
     */
    public function aggregate(int $interviewId, array $verdicts, float $passThreshold = 60.0): array
    {
        $criteria = [];
        $rawScores = [];

        foreach ($verdicts as $key => $verdict) {
            if (($verdict['agent_type'] ?? 'scoring') !== 'scoring') {
                continue; // the aggregator agent contributes no score
            }
            $criteria[] = [
                'key'    => $key,
                'label'  => $key,
                'weight' => (float) ($verdict['weight'] ?? 0),
                'max'    => (float) ($verdict['max_score'] ?? 0),
            ];
            $rawScores[$key] = (float) ($verdict['score'] ?? 0);
        }

        return (new DecisionEngine())->score($criteria, $rawScores, $passThreshold);
    }

    /**
     * The effective agent catalog: active system rows (workspace_id IS NULL) merged
     * with the current tenant's active overrides (same key wins). Each descriptor is
     * enriched with the lens from config/ai_agents.php.
     *
     * @param array<int,string> $only restrict to these keys.
     * @return array<string, array<string,mixed>> descriptor keyed by agent key.
     */
    private function resolveAgents(array $only = []): array
    {
        $tenantId = tenant()->id();

        // Load active rows ordered by sort_order, then partition in PHP into system
        // (workspace_id IS NULL) and the CURRENT tenant's overrides — rows belonging
        // to any other tenant are ignored (tenant safety on an unscoped model).
        $rows = $this->db->table('ai_agents')
            ->where('is_active', '=', 1)
            ->orderBy('sort_order')
            ->get();

        $system = [];
        $tenant = [];
        foreach ($rows as $row) {
            if ($row['workspace_id'] === null) {
                $system[$row['key']] = $row;
            } elseif ($tenantId !== null && (int) $row['workspace_id'] === $tenantId) {
                $tenant[$row['key']] = $row;
            }
        }
        $merged = array_merge($system, $tenant); // tenant overrides system

        $lenses = (array) config('ai_agents.agents', []);
        $catalog = [];
        foreach ($merged as $key => $row) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }
            $lens = $lenses[$key]['lens'] ?? null;
            $catalog[$key] = [
                'key'        => (string) $row['key'],
                'agent_type' => (string) $row['agent_type'],
                'weight'     => (float) $row['weight'],
                'lens'       => (string) ($lens ?? ($row['description'] ?? 'You are an interview evaluator.')),
            ];
        }

        return $catalog;
    }

    /**
     * Persist one immutable agent_runs row (§15). Tenant + uuid are stamped by the
     * Model layer; verdict is JSON-encoded on write.
     *
     * @param array<string,mixed> $verdict
     */
    private function record(int $interviewId, string $agentKey, array $verdict): AgentRun
    {
        return AgentRun::create([
            'interview_id'      => $interviewId,
            'agent_key'         => $agentKey,
            'agent_type'        => $verdict['agent_type'] ?? null,
            'score'             => $verdict['score'] ?? null,
            'max_score'         => $verdict['max_score'] ?? null,
            'confidence'        => $verdict['confidence'] ?? null,
            'verdict'           => isset($verdict['verdict'])
                ? json_encode($verdict['verdict'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'rationale'         => $verdict['rationale'] ?? null,
            'model_key'         => $verdict['model_key'] ?? null,
            'prompt_tokens'     => $verdict['prompt_tokens'] ?? null,
            'completion_tokens' => $verdict['completion_tokens'] ?? null,
            'created_at'        => now(),
        ]);
    }
}
