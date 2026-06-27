<?php

declare(strict_types=1);

namespace App\Contracts\AI;

/**
 * A single-responsibility interview agent (docs/51 §2). Each implementation judges
 * ONE lens (HR / technical / behaviour / …) of an interview and returns a uniform,
 * recordable verdict so the AgentRunner can persist it (§15) and the Decision
 * Engine can aggregate it (§3). Implementations call the AiGateway for the model
 * judgement but must remain offline-testable — the structured score is derived
 * deterministically, never parsed from raw model JSON.
 */
interface InterviewAgent
{
    /** Stable agent key, e.g. 'hr', 'technical', 'decision'. */
    public function key(): string;

    /** Agent type, e.g. 'scoring' or 'decision'. */
    public function type(): string;

    /**
     * Evaluate the interview context for this agent's lens.
     *
     * @param array<string,mixed> $context interview signals — typically
     *        `answer`/`transcript` (the candidate text) and an optional
     *        `scores` map of pre-computed per-agent raw scores.
     * @return array{score:float, max_score:float, confidence:float,
     *               verdict:array<string,mixed>, rationale:string}
     */
    public function evaluate(array $context): array;
}
