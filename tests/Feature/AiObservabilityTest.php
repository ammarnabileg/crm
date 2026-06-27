<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;
use App\Services\AI\ModelRouter;
use App\Services\AI\PromptGuard;
use App\Services\AI\Providers\FakeProvider;
use App\Services\AI\TokenOptimizer;
use App\Services\Observability\AiObservability;
use App\Services\Observability\BenchmarkEngine;
use App\Services\Observability\CostOptimizer;
use App\Services\Simulation\SimulationMode;
use Tests\TestCase;

/**
 * AI Interview Engine P9 — Observability + Cost Optimizer + Model Benchmark +
 * Simulation/Sandbox (docs/51 §17, §19). Exercised offline via the FakeProvider:
 * the gateway audit feeds the observability dashboard, the cost optimizer ranks
 * the tenant's models, the benchmark engine compares engines on a scenario, and
 * the sandbox dry-runs a mock interview WITHOUT persisting anything.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    // --- (a) Observability --------------------------------------------------

    public function test_observability_metrics_aggregate_requests(): void
    {
        $this->makeKey('anthropic', true);

        // One successful and one failing attempt → both audited by the gateway.
        $okGateway = $this->gateway()->register(new FakeProvider('anthropic', false, 'Great answer'));
        $okGateway->complete($this->userPrompt('Tell me about your experience.'), [
            'candidates' => [['provider' => 'anthropic', 'model' => 'claude-3-7-sonnet']],
        ]);

        $failGateway = $this->gateway()->register(new FakeProvider('anthropic', true));
        $failGateway->complete($this->userPrompt('Another question.'), [
            'candidates' => [['provider' => 'anthropic', 'model' => 'claude-3-7-sonnet']],
        ]);

        $metrics = AiObservability::make()->metrics();

        $this->assertTrue($metrics['total_requests'] >= 2);
        $this->assertTrue($metrics['success_count'] >= 1);
        $this->assertTrue($metrics['failure_count'] >= 1);
        // Fallback count approximates failures.
        $this->assertSame($metrics['failure_count'], $metrics['fallback_count']);
        // Per-provider breakdown includes anthropic.
        $this->assertTrue(array_key_exists('anthropic', $metrics['by_provider']));
        $this->assertTrue($metrics['by_provider']['anthropic'] >= 2);
        // Token totals are populated from the successful response.
        $this->assertTrue($metrics['total_prompt_tokens'] >= 1);
        $this->assertTrue($metrics['success_rate'] > 0.0);
    }

    public function test_observability_is_tenant_scoped_and_empty_by_default(): void
    {
        // No requests created in this test → all counters zero/null.
        $metrics = AiObservability::make()->metrics();

        $this->assertSame(0, $metrics['total_requests']);
        $this->assertSame(0, $metrics['failure_count']);
        $this->assertSame(0.0, $metrics['success_rate']);
        $this->assertNull($metrics['avg_prompt_tokens']);
        $this->assertNull($metrics['avg_latency_ms']);
        $this->assertNull($metrics['estimated_cost']);
    }

    // --- (b) Cost Optimizer -------------------------------------------------

    public function test_cost_optimizer_estimates_cost_as_non_negative_float(): void
    {
        $optimizer = CostOptimizer::make();
        $cost = $optimizer->estimateCost('claude-3-7-sonnet', 1000, 256);

        $this->assertTrue(is_float($cost));
        $this->assertTrue($cost >= 0.0);
    }

    public function test_cost_optimizer_picks_a_cheapest_candidate(): void
    {
        $this->makeKey('anthropic', true);

        $cheapest = CostOptimizer::make()->cheapest('chat');

        $this->assertNotNull($cheapest);
        $this->assertSame('anthropic', $cheapest['provider']);
        $this->assertTrue(array_key_exists('estimated_cost', $cheapest));
        $this->assertTrue($cheapest['estimated_cost'] >= 0.0);
    }

    public function test_cost_optimizer_returns_null_without_keys(): void
    {
        $this->assertNull(CostOptimizer::make()->cheapest('chat'));
    }

    // --- (c) Benchmark Engine ----------------------------------------------

    public function test_benchmark_runs_two_engines_and_recommends_one(): void
    {
        $engine = BenchmarkEngine::make();
        $benchmark = $engine->create(
            'Engine Comparison',
            'Evaluate this candidate answer for clarity and depth.',
            ['weights' => ['quality' => 1.0]]
        );

        $this->assertNotNull($benchmark->getKey());

        $gateway = $this->gateway()
            ->register(new FakeProvider('anthropic', false, 'Answer from anthropic'))
            ->register(new FakeProvider('openai', false, 'Answer from openai'));

        $results = $engine->run((int) $benchmark->getKey(), [
            ['provider' => 'anthropic', 'model' => 'claude-3-7-sonnet'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        ], $gateway);

        $this->assertSame(2, count($results));

        $stored = app('db')->table('ai_benchmark_results')
            ->where('benchmark_id', '=', (int) $benchmark->getKey())
            ->count();
        $this->assertSame(2, $stored);

        $recommended = $engine->recommend((int) $benchmark->getKey(), 'quality_score');
        $this->assertNotNull($recommended);
        $this->assertTrue(in_array($recommended['provider'], ['anthropic', 'openai'], true));
        $this->assertTrue((float) $recommended['quality_score'] > 0.0);
    }

    // --- (d) Simulation / Sandbox ------------------------------------------

    public function test_simulation_runs_mock_interview_without_persisting(): void
    {
        $before = $this->interviewCount();

        $gateway = AiGateway::make(); // self-registers the offline 'sandbox' provider
        $sim = new SimulationMode($gateway);
        $out = $sim->runMockInterview(['turns' => 2]);

        $this->assertTrue($out['sandbox']);
        $this->assertSame(2, $out['turns']);
        $this->assertSame(2, count($out['transcript']));
        $this->assertTrue($out['transcript'][0]['ok']);
        $this->assertNotNull($out['candidate']['name']);

        // The sandbox persists nothing: no interview rows were written.
        $this->assertSame($before, $this->interviewCount());
    }

    // --- helpers ------------------------------------------------------------

    private function gateway(): AiGateway
    {
        return new AiGateway(app('db'), new PromptGuard(), new TokenOptimizer(), ModelRouter::make());
    }

    private function userPrompt(string $content): AiPrompt
    {
        return new AiPrompt([
            ['role' => 'system', 'content' => 'You are an interviewer.', 'trusted' => true],
            ['role' => 'user', 'content' => $content],
        ], 'chat');
    }

    private function makeKey(string $provider, bool $default): AiCredential
    {
        return AiCredential::create([
            'provider'    => $provider,
            'label'       => $provider . ' key',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'sk-test-' . $provider]),
            'is_active'   => 1,
            'is_default'  => $default ? 1 : 0,
        ]);
    }

    private function interviewCount(): int
    {
        return app('db')->table('interviews')
            ->where('workspace_id', '=', tenant()->id())
            ->count();
    }
};
