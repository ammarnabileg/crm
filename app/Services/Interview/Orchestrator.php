<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;
use App\Services\AI\AiResult;

/**
 * AI Orchestrator (docs/51 §1) — the per-turn driver of an AI interview.
 *
 * It is the conductor that wires the engines together for a single turn:
 *   1. records the candidate's answer in the Memory Engine (§4);
 *   2. composes a prompt from a TRUSTED system message grounded by the Knowledge
 *      Engine (§11) plus the Memory Engine's BOUNDED context (summary + recent
 *      turns — never the full transcript) and the candidate's answer as an
 *      UNTRUSTED user message;
 *   3. calls the model strictly through the AiGateway (§12 — guard → optimize →
 *      route → fallback → audit), so it is provider-agnostic and never touches a
 *      key or a raw provider;
 *   4. runs AI Quality Control (§14) on the output and only persists it to memory
 *      when it passes.
 *
 * Prior memory turns are marked `trusted=false` when a candidate turn is in play so
 * the gateway's PromptGuard re-hardens them; this keeps replayed candidate text as
 * data, not instructions.
 */
final class Orchestrator
{
    private readonly AiGateway $gateway;

    public function __construct(
        ?AiGateway $gateway = null,
        private readonly MemoryEngine $memory = new MemoryEngine(),
        private readonly KnowledgeEngine $knowledge = new KnowledgeEngine(),
        private readonly QualityControl $quality = new QualityControl(),
    ) {
        // AiGateway has no constructible default expression (it needs runtime
        // collaborators), so default it here via its factory.
        $this->gateway = $gateway ?? AiGateway::make();
    }

    /** Build a fully-wired orchestrator with the default gateway and engines. */
    public static function make(): self
    {
        return new self(
            AiGateway::make(),
            new MemoryEngine(),
            new KnowledgeEngine(),
            new QualityControl(),
        );
    }

    /**
     * Drive one interview turn and return the model result.
     *
     * @param array<string,mixed> $context extra gateway context (merged with the
     *                                      interview subject binding)
     */
    public function ask(int $interviewId, ?string $candidateAnswer = null, array $context = []): AiResult
    {
        $hasCandidateTurn = $candidateAnswer !== null;

        // (b-pre) Snapshot the BOUNDED prior memory BEFORE recording the new answer,
        // so the candidate turn is not duplicated (once in memory, once trailing).
        $priorMessages = $this->memory->buildContext($interviewId);

        // (a) Record the candidate's answer in memory.
        if ($hasCandidateTurn) {
            $this->memory->appendItem($interviewId, 'user', 'message', $candidateAnswer);
        }

        // (b) Compose the prompt.
        $knowledgeContext = $this->knowledge->buildKnowledgeContext($interviewId);
        $systemContent = 'You are an interviewer.';
        if (trim($knowledgeContext) !== '') {
            $systemContent .= "\n\n" . $knowledgeContext;
        }

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemContent, 'trusted' => true];

        foreach ($priorMessages as $m) {
            // Re-harden replayed turns when a candidate turn is in play.
            if ($hasCandidateTurn && ($m['role'] ?? '') !== 'system') {
                $m['trusted'] = false;
            }
            $messages[] = $m;
        }

        if ($hasCandidateTurn) {
            $messages[] = ['role' => 'user', 'content' => $candidateAnswer, 'trusted' => false];
        }

        $prompt = new AiPrompt($messages, 'chat');

        // (c) Call the model — only ever through the gateway.
        $result = $this->gateway->complete($prompt, array_merge([
            'subject_type' => 'interview',
            'subject_id'   => $interviewId,
        ], $context));

        // (d) Quality-control the output; (e) persist only when it passes.
        if ($result->ok) {
            $check = $this->quality->check($result->text);
            if ($check['ok']) {
                $this->memory->appendItem($interviewId, 'assistant', 'message', $result->text);
            }
        }

        // (f) Return the raw result regardless (caller decides on failures).
        return $result;
    }
}
