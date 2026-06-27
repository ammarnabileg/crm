<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Token Optimizer (docs/51 §13) — trims a request to `context + summary + recent`
 * before it is sent, capping latency, tokens and cost. It removes empty messages,
 * collapses repeated whitespace, drops consecutive duplicate messages (the same
 * instruction/context resent), and bounds history to the most recent N messages
 * while always preserving leading system/trusted messages.
 *
 * Pure and deterministic — no model call.
 */
final class TokenOptimizer
{
    /**
     * @param array<int, array<string,mixed>> $messages
     * @return array<int, array<string,mixed>>
     */
    public function optimize(array $messages): array
    {
        $maxMessages = (int) config('ai.optimizer.max_messages', 40);

        // 1) Normalize: drop empties, collapse whitespace.
        $normalized = [];
        foreach ($messages as $m) {
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
            $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
            $m['content'] = $content;
            $normalized[] = $m;
        }

        // 2) Drop consecutive duplicate (role+content) messages.
        $deduped = [];
        $prevKey = null;
        foreach ($normalized as $m) {
            $key = ($m['role'] ?? '') . '|' . $m['content'];
            if ($key === $prevKey) {
                continue;
            }
            $deduped[] = $m;
            $prevKey = $key;
        }

        // 3) Bound history: keep leading system/trusted messages + the most recent
        //    tail so the total fits the cap.
        if (count($deduped) <= $maxMessages) {
            return $deduped;
        }

        // Protected head = the contiguous leading run of system/trusted messages.
        $head = [];
        $i = 0;
        $n = count($deduped);
        while ($i < $n && (($deduped[$i]['role'] ?? '') === 'system' || ! empty($deduped[$i]['trusted']))) {
            $head[] = $deduped[$i];
            $i++;
        }

        $rest = array_slice($deduped, $i);
        $tailBudget = max(1, $maxMessages - count($head));
        $tail = array_slice($rest, -$tailBudget);

        return array_merge($head, $tail);
    }

    /** Rough token estimate for a string (chars / chars-per-token). */
    public function estimateTokens(string $text): int
    {
        $perToken = max(1, (int) config('ai.optimizer.chars_per_token', 4));

        return (int) ceil(mb_strlen($text) / $perToken);
    }

    /** Rough token estimate for a set of messages. */
    public function estimateMessageTokens(array $messages): int
    {
        $total = 0;
        foreach ($messages as $m) {
            $total += $this->estimateTokens((string) ($m['content'] ?? ''));
        }

        return $total;
    }
}
