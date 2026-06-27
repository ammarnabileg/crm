<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Model;
use App\Models\AiCredential;
use App\Services\AI\AiGateway;
use App\Services\AI\ModelRouter;
use App\Services\AI\PromptGuard;
use App\Services\AI\Providers\FakeProvider;
use App\Services\AI\TokenOptimizer;
use App\Services\Agents\AgentRunner;
use Tests\TestCase;

/**
 * AI Interview Engine P6 — Multi-Agent layer + Explainable AI + Decision
 * aggregation (docs/51 §2, §3, §15). Exercises the nine seeded agents, the
 * AgentRunner's record-per-agent run path, and the DecisionEngine-backed
 * aggregation — all offline via a FakeProvider (no live keys, no network).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    // --- catalog ------------------------------------------------------------

    public function test_nine_system_agents_are_seeded(): void
    {
        $count = (int) app('db')->table('ai_agents')
            ->where('is_system', '=', 1)
            ->whereNull('workspace_id')
            ->count();

        $this->assertSame(9, $count);
    }

    public function test_runner_resolves_nine_agents(): void
    {
        $runner = $this->runner();
        $verdicts = $runner->run($this->makeInterview(), ['answer' => 'I led a team that shipped a payments API.']);

        $this->assertSame(9, count($verdicts));
        $this->assertTrue(array_key_exists('technical', $verdicts));
        $this->assertTrue(array_key_exists('decision', $verdicts));
    }

    // --- run() records one agent_runs row per agent (§15) -------------------

    public function test_run_records_one_agent_run_per_agent(): void
    {
        $interviewId = $this->makeInterview();
        $db = app('db');

        $before = $db->table('agent_runs')->where('interview_id', '=', $interviewId)->count();
        $this->runner()->run($interviewId, ['answer' => 'Five years building PHP services.']);
        $after = $db->table('agent_runs')->where('interview_id', '=', $interviewId)->count();

        $this->assertSame($before + 9, $after);

        // The recorded rows carry the tenant + a structured verdict.
        $row = $db->table('agent_runs')
            ->where('interview_id', '=', $interviewId)
            ->where('agent_key', '=', 'technical')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(tenant()->id(), (int) $row['workspace_id']);
        $this->assertNotNull($row['verdict']);
    }

    public function test_run_can_restrict_to_specific_agents(): void
    {
        $interviewId = $this->makeInterview();
        $verdicts = $this->runner()->run($interviewId, ['answer' => 'Hello'], ['hr', 'technical']);

        $this->assertSame(2, count($verdicts));
        $this->assertSame(
            2,
            app('db')->table('agent_runs')->where('interview_id', '=', $interviewId)->count()
        );
    }

    // --- aggregate() → explainable decision (§3, §15) ----------------------

    public function test_aggregate_produces_normalized_score_and_recommendation(): void
    {
        $interviewId = $this->makeInterview();
        $verdicts = $this->runner()->run($interviewId, ['answer' => 'A strong, well-structured answer.']);

        $decision = $this->runner()->aggregate($interviewId, $verdicts, 60.0);

        $this->assertTrue($decision['normalized_score'] >= 0.0);
        $this->assertTrue($decision['normalized_score'] <= 100.0);
        $this->assertTrue(is_string($decision['recommendation_key']));
        $this->assertTrue(is_bool($decision['passed']));
    }

    public function test_explainable_factors_count_equals_scoring_agents(): void
    {
        $interviewId = $this->makeInterview();
        $verdicts = $this->runner()->run($interviewId, ['answer' => 'Detailed answer.']);

        $decision = $this->runner()->aggregate($interviewId, $verdicts);

        // Eight scoring agents contribute a factor; the 'decision' aggregator does not.
        $scoring = 0;
        foreach ($verdicts as $v) {
            if (($v['agent_type'] ?? '') === 'scoring') {
                $scoring++;
            }
        }
        $this->assertSame(8, $scoring);
        $this->assertSame($scoring, count($decision['factors']));
        $this->assertSame($scoring, $decision['scored_count']);
    }

    public function test_aggregate_is_deterministic_with_explicit_scores(): void
    {
        $interviewId = $this->makeInterview();

        // Drive every scoring agent to a perfect score → normalized 100 → strong_yes.
        $scores = [];
        foreach (['hr', 'technical', 'behavior', 'psychometric', 'communication', 'language', 'culture_fit', 'risk'] as $k) {
            $scores[$k] = 100.0;
        }

        $verdicts = $this->runner()->run($interviewId, ['answer' => 'x', 'scores' => $scores]);
        $decision = $this->runner()->aggregate($interviewId, $verdicts, 60.0);

        $this->assertSame(100.0, $decision['normalized_score']);
        $this->assertTrue($decision['passed']);
        $this->assertSame('strong_yes', $decision['recommendation_key']);
    }

    // --- helpers ------------------------------------------------------------

    /** A runner whose gateway routes to a registered offline FakeProvider. */
    private function runner(): AgentRunner
    {
        $this->makeKey('anthropic', true);
        $gateway = new AiGateway(app('db'), new PromptGuard(), new TokenOptimizer(), ModelRouter::make());
        $gateway->register(new FakeProvider('anthropic', false, 'OK'));

        return new AgentRunner(app('db'), $gateway);
    }

    private function makeKey(string $provider, bool $default): AiCredential
    {
        // Idempotent: runner() may be called more than once per test.
        $existing = AiCredential::findBy('provider', $provider);
        if ($existing !== null) {
            return $existing;
        }

        return AiCredential::create([
            'provider'    => $provider,
            'label'       => $provider . ' key',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'sk-test-' . $provider]),
            'is_active'   => 1,
            'is_default'  => $default ? 1 : 0,
        ]);
    }

    /**
     * Build the minimal valid FK chain for one interview (workspace → job →
     * application → interview) and return its id. Rolled back with the test tx.
     */
    private function makeInterview(): int
    {
        $db = app('db');
        $workspaceId = (int) $db->table('workspaces')->orderBy('id')->value('id');
        $userId = (int) $db->table('users')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
        $now = now();

        $jobId = $db->table('jobs')->insertGetId([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'job_status_id' => status_id('job_statuses', 'open'),
            'title'         => 'Agent Runner Test Job',
            'slug'          => 'ar-test-' . substr(Model::generateUuid(), 0, 12),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $applicationId = $db->table('applications')->insertGetId([
            'uuid'                  => Model::generateUuid(),
            'workspace_id'          => $workspaceId,
            'job_id'                => $jobId,
            'user_id'               => $userId,
            'application_status_id' => status_id('application_statuses', 'applied'),
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        return $db->table('interviews')->insertGetId([
            'uuid'                => Model::generateUuid(),
            'workspace_id'        => $workspaceId,
            'application_id'      => $applicationId,
            'job_id'             => $jobId,
            'type_id'            => lookup_id('interview_type', 'video'),
            'mode_id'            => lookup_id('interview_mode', 'remote'),
            'interview_status_id' => status_id('interview_statuses', 'scheduled'),
            'created_by'         => $userId,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }
};
