<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Shared\Ulid;

/**
 * The conversational AI interview room (candidate spec, page 2, mode A — text).
 * A turn-by-turn interview: the AI greets, explains the role, and asks one
 * question at a time; the candidate answers; the room deepens and then closes
 * automatically after the question budget or the time window — whichever comes
 * first. The candidate may leave and resume within the window. Every result is
 * advisory; a human always decides (docs/AI_ENGINE.md §8.1).
 *
 * Voice (mode B) and live-avatar video (mode C) are presentation layers over this
 * same engine: the questions, budget, timing and scoring are identical.
 */
final class InterviewRoomService
{
    public const MAX_QUESTIONS = 12;
    public const DURATION_MINUTES = 20;

    public function __construct(
        private readonly Connection $connection,
        private readonly AiEngine $ai,
        private readonly AssessmentService $assessments,
    ) {
    }

    /**
     * Start (or resume) the room. Idempotent: a second call within the window
     * returns the current state without re-greeting.
     *
     * @return array<string, mixed>
     */
    public function begin(string $workspaceId, string $interviewId, ?string $actorUserId = null): array
    {
        $iv = $this->load($workspaceId, $interviewId);
        if ((string) $iv['type'] !== 'ai') {
            throw new ApplicationException('This is not an AI interview.');
        }
        if ((string) $iv['status'] === 'completed') {
            return $this->state($workspaceId, $interviewId);
        }

        if (empty($iv['started_at'])) {
            $now = gmdate('Y-m-d H:i:s');
            $questions = $this->planQuestions($workspaceId, $interviewId, (string) ($iv['job_title'] ?? ''), $actorUserId);
            $details = $this->details($iv);
            $details['questions'] = $questions;
            $this->connection->statement(
                "UPDATE interviews SET status = 'in_progress', started_at = ?, details = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
                [$now, json_encode($details), $now, $interviewId, $workspaceId],
            );

            $name = (string) ($iv['candidate_name'] ?? 'there');
            $title = (string) ($iv['job_title'] ?? 'this role');
            $minutes = self::DURATION_MINUTES;
            $this->append($workspaceId, $interviewId, 'ai', "Hi {$name}, thanks for joining. I'm your AI interviewer for the {$title} position. I'll ask a few questions — answer in your own words, and take your time. You can pause and come back within {$minutes} minutes. Let's begin.");
            $this->append($workspaceId, $interviewId, 'ai', $questions[0]);
        }

        return $this->state($workspaceId, $interviewId);
    }

    /**
     * Record the candidate's answer and advance the conversation, finalizing the
     * interview when the budget or the clock runs out.
     *
     * @return array<string, mixed>
     */
    public function answer(string $workspaceId, string $interviewId, string $text, ?string $actorUserId = null): array
    {
        $iv = $this->load($workspaceId, $interviewId);
        if ((string) $iv['status'] === 'completed') {
            return $this->state($workspaceId, $interviewId);
        }
        if (empty($iv['started_at'])) {
            $this->begin($workspaceId, $interviewId, $actorUserId);
            $iv = $this->load($workspaceId, $interviewId);
        }

        $text = trim($text);
        if ($text !== '') {
            $this->append($workspaceId, $interviewId, 'candidate', mb_substr($text, 0, 4000));
        }

        $questions = $this->details($iv)['questions'] ?? [];
        $asked = $this->countAsked($interviewId);          // AI questions already posed
        $timeUp = $this->secondsRemaining($iv) <= 0;

        if ($asked >= count($questions) || $asked >= self::MAX_QUESTIONS || $timeUp) {
            $this->finalize($workspaceId, $interviewId, $actorUserId);
        } else {
            $this->append($workspaceId, $interviewId, 'ai', (string) $questions[$asked]);
        }

        return $this->state($workspaceId, $interviewId);
    }

    /**
     * Current room state for rendering.
     *
     * @return array{messages: list<array<string,mixed>>, asked: int, max_questions: int, seconds_remaining: int, done: bool, score: ?int}
     */
    public function state(string $workspaceId, string $interviewId): array
    {
        $iv = $this->load($workspaceId, $interviewId);
        $messages = $this->connection->select(
            'SELECT role, content, position FROM interview_messages WHERE interview_id = ? AND workspace_id = ? ORDER BY position ASC',
            [$interviewId, $workspaceId],
        );

        return [
            'messages' => $messages,
            'asked' => $this->countAsked($interviewId),
            'max_questions' => self::MAX_QUESTIONS,
            'seconds_remaining' => $this->secondsRemaining($iv),
            'done' => (string) $iv['status'] === 'completed',
            'score' => isset($iv['score']) && $iv['score'] !== null ? (int) $iv['score'] : null,
        ];
    }

    /** Close the interview: persist the transcript, score it, run the assessment. */
    public function finalize(string $workspaceId, string $interviewId, ?string $actorUserId = null): void
    {
        $iv = $this->load($workspaceId, $interviewId);
        if ((string) $iv['status'] === 'completed') {
            return;
        }

        $this->append($workspaceId, $interviewId, 'ai', "That's everything from my side — thank you for your time and thoughtful answers. The hiring team will review this and follow up. This interview is now complete.");

        $transcript = $this->transcript($workspaceId, $interviewId);
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            "UPDATE interviews SET status = 'completed', transcript = ?, completed_at = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
            [$transcript, $now, $now, $interviewId, $workspaceId],
        );

        // Advisory scoring + structured assessment (best-effort; the transcript stands).
        try {
            $assessment = $this->assessments->assessFromInterview($workspaceId, $interviewId, $actorUserId);
            $this->connection->statement(
                'UPDATE interviews SET score = ?, recommendation = ?, ai_provider = COALESCE(ai_provider, ?), updated_at = ? WHERE id = ? AND workspace_id = ?',
                [(int) ($assessment['fit_score'] ?? 0), (string) ($assessment['recommendation'] ?? 'hold'), (string) ($assessment['ai_provider'] ?? null), $now, $interviewId, $workspaceId],
            );
        } catch (\Throwable) {
            // Assessment is best-effort.
        }
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function planQuestions(string $workspaceId, string $interviewId, string $jobTitle, ?string $actorUserId): array
    {
        $defaults = [
            'To start, tell me a bit about yourself and your background.',
            'What attracted you to this role?',
            "Walk me through a project you're proud of and your specific contribution.",
            'Describe a hard problem you solved recently. How did you approach it?',
            'How do you handle disagreements within a team?',
            'Tell me about a time you took ownership of something beyond your role.',
            'How do you keep your skills current?',
            'Describe a mistake you made and what you learned from it.',
            'How do you prioritise when everything feels urgent?',
            'What does great collaboration look like to you?',
            'Where do you want to grow over the next two years?',
            "Is there anything you'd like us to know that we haven't covered?",
        ];

        // Use the AI engine's role-specific questions when a real provider returns
        // usable ones; otherwise fall back to the deterministic defaults.
        try {
            $result = $this->ai->run($workspaceId, 'interview_questions', ['title' => $jobTitle], $actorUserId);
            $parsed = $this->parseQuestions($result->text);
            if (count($parsed) >= 6) {
                return array_slice($parsed, 0, self::MAX_QUESTIONS);
            }
        } catch (\Throwable) {
            // fall through to defaults
        }

        return array_slice($defaults, 0, self::MAX_QUESTIONS);
    }

    /** @return list<string> */
    private function parseQuestions(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/^\s*(?:\d+[\.\)]|[-*•])\s*/', '', $line));
            if (mb_strlen($line) >= 12 && str_contains($line, '?')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function append(string $workspaceId, string $interviewId, string $role, string $content): void
    {
        $position = (int) (($this->connection->selectOne('SELECT COALESCE(MAX(position), -1) AS p FROM interview_messages WHERE interview_id = ?', [$interviewId])['p']) ?? -1) + 1;
        $this->connection->statement(
            'INSERT INTO interview_messages (id, workspace_id, interview_id, role, content, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $workspaceId, $interviewId, $role, $content, $position, gmdate('Y-m-d H:i:s')],
        );
    }

    private function countAsked(string $interviewId): int
    {
        // The greeting is an AI message too; questions are AI messages after it.
        $aiCount = (int) ($this->connection->selectOne("SELECT COUNT(*) AS c FROM interview_messages WHERE interview_id = ? AND role = 'ai'", [$interviewId])['c'] ?? 0);

        return max(0, $aiCount - 1); // exclude the greeting
    }

    private function secondsRemaining(array $iv): int
    {
        if (empty($iv['started_at'])) {
            return self::DURATION_MINUTES * 60;
        }
        $elapsed = time() - strtotime((string) $iv['started_at'] . ' UTC');

        return max(0, self::DURATION_MINUTES * 60 - $elapsed);
    }

    private function transcript(string $workspaceId, string $interviewId): string
    {
        $rows = $this->connection->select(
            'SELECT role, content FROM interview_messages WHERE interview_id = ? AND workspace_id = ? ORDER BY position ASC',
            [$interviewId, $workspaceId],
        );
        $lines = [];
        foreach ($rows as $r) {
            $who = (string) $r['role'] === 'candidate' ? 'Candidate' : 'Interviewer';
            $lines[] = "{$who}: {$r['content']}";
        }

        return implode("\n", $lines);
    }

    /** @return array<string,mixed> */
    private function details(array $iv): array
    {
        if (empty($iv['details'])) {
            return [];
        }

        return is_array($iv['details']) ? $iv['details'] : (json_decode((string) $iv['details'], true) ?: []);
    }

    /** @return array<string,mixed> */
    private function load(string $workspaceId, string $interviewId): array
    {
        $iv = $this->connection->selectOne(
            'SELECT i.*, u.name AS candidate_name, j.title AS job_title
               FROM interviews i
               JOIN users u ON u.id = i.candidate_user_id
               JOIN jobs j ON j.id = i.job_id
              WHERE i.id = ? AND i.workspace_id = ? AND i.deleted_at IS NULL',
            [$interviewId, $workspaceId],
        );
        if ($iv === null) {
            throw new ApplicationException('Interview not found in this workspace.');
        }

        return $iv;
    }
}
