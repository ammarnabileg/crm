<?php

declare(strict_types=1);

namespace App\Services\Simulation;

use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;
use App\Services\AI\Providers\FakeProvider;

/**
 * Simulation / Sandbox Mode (docs/51 §19) — a zero-cost, zero-risk dry-run of an
 * AI interview. It fabricates a DUMMY candidate, a FAKE CV and DETERMINISTIC
 * answers, then drives a few question/answer turns through an {@see AiGateway} so
 * a workspace (or a test) can preview the engine end-to-end without a live model,
 * without spending tokens, and — crucially — WITHOUT touching production data.
 *
 * It is sandboxed by construction: it persists NOTHING. Every turn is held in an
 * in-memory transcript and returned to the caller tagged `sandbox = true`; no
 * interview, application, response or audit row is written by this service. By
 * default it self-provisions an offline {@see FakeProvider} so it runs even when
 * the tenant has no keys, but a pre-configured gateway may be injected.
 */
final class SimulationMode
{
    /** Deterministic question bank for the mock interview. */
    private const QUESTIONS = [
        'Tell me about your most relevant experience for this role.',
        'Describe a difficult technical problem you solved and how.',
        'How do you approach collaborating with a cross-functional team?',
    ];

    public function __construct(private readonly AiGateway $gateway)
    {
    }

    public static function make(): self
    {
        // The injected gateway already registers an offline 'sandbox' provider.
        return new self(AiGateway::make());
    }

    /**
     * Run a deterministic mock interview entirely in memory.
     *
     * @param array{candidate_name?:string, role?:string, turns?:int, provider?:string, model?:string} $options
     * @return array<string,mixed> transcript + sandbox flag (nothing persisted)
     */
    public function runMockInterview(array $options = []): array
    {
        $candidate = $this->fakeCandidate($options);
        $role = (string) ($options['role'] ?? 'Software Engineer');
        $turns = max(1, min(count(self::QUESTIONS), (int) ($options['turns'] ?? count(self::QUESTIONS))));

        // Always have a runnable offline provider; the caller may name another.
        $provider = (string) ($options['provider'] ?? 'sandbox');
        $model = (string) ($options['model'] ?? 'sandbox-1');
        $this->gateway->register(new FakeProvider($provider, false, 'Simulated evaluation: candidate response noted.'));

        $transcript = [];
        $okTurns = 0;
        for ($i = 0; $i < $turns; $i++) {
            $question = self::QUESTIONS[$i];
            $answer = $this->fakeAnswer($candidate, $role, $i);

            $prompt = new AiPrompt([
                ['role' => 'system', 'content' => 'You are a sandbox interviewer evaluating a candidate.', 'trusted' => true],
                ['role' => 'assistant', 'content' => $question, 'trusted' => true],
                ['role' => 'user', 'content' => $answer],
            ], 'chat');

            $result = $this->gateway->complete($prompt, [
                'candidates' => [['provider' => $provider, 'model' => $model]],
            ]);
            if ($result->ok) {
                $okTurns++;
            }

            $transcript[] = [
                'turn'       => $i + 1,
                'question'   => $question,
                'answer'     => $answer,
                'evaluation' => $result->ok ? $result->text : null,
                'ok'         => $result->ok,
                'error'      => $result->ok ? null : $result->error,
            ];
        }

        return [
            'sandbox'   => true,
            'candidate' => $candidate,
            'role'      => $role,
            'turns'     => count($transcript),
            'ok_turns'  => $okTurns,
            'transcript' => $transcript,
        ];
    }

    /**
     * Build a deterministic dummy candidate + fake CV (no real PII, no DB row).
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function fakeCandidate(array $options): array
    {
        $name = (string) ($options['candidate_name'] ?? 'Sandbox Candidate');

        return [
            'name'  => $name,
            'email' => 'sandbox+' . strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name) ?: 'candidate') . '@example.test',
            'cv'    => [
                'summary'    => 'Experienced engineer (simulated profile for sandbox use only).',
                'years'      => 5,
                'skills'     => ['PHP', 'MySQL', 'System Design', 'Testing'],
                'experience' => [
                    ['title' => 'Senior Engineer', 'company' => 'Acme (simulated)', 'years' => 3],
                    ['title' => 'Engineer', 'company' => 'Globex (simulated)', 'years' => 2],
                ],
            ],
        ];
    }

    /** Deterministic candidate answer text for a given turn. */
    private function fakeAnswer(array $candidate, string $role, int $turn): string
    {
        $years = (int) ($candidate['cv']['years'] ?? 5);

        return sprintf(
            'As a %s with %d years of experience, in answer #%d I would draw on my work with PHP and MySQL to deliver a measurable result.',
            $role,
            $years,
            $turn + 1
        );
    }
}
