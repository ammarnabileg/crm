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
        private readonly ApplicationService $applications,
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
            $questions = $this->planQuestions($workspaceId, (string) ($iv['job_id'] ?? ''), (string) ($iv['job_title'] ?? ''), $actorUserId);
            $details = $this->details($iv);
            $details['questions'] = $questions;
            $this->connection->statement(
                "UPDATE interviews SET status = 'in_progress', started_at = ?, details = ?, updated_at = ? WHERE id = ? AND workspace_id = ?",
                [$now, json_encode($details), $now, $interviewId, $workspaceId],
            );

            $name = (string) ($iv['candidate_name'] ?? 'there');
            $title = (string) ($iv['job_title'] ?? 'this role');
            $minutes = $this->durationMinutes($iv);
            $this->append($workspaceId, $interviewId, 'ai', "Hi {$name}, thanks for joining. I'm your AI interviewer for the {$title} position. I'll ask a few questions — answer in your own words, and take your time. You can pause and come back within {$minutes} minutes. Let's begin.");
            // First question: AI-generated from the CV + job when a real provider is
            // configured; otherwise the planned/static opener.
            $this->append($workspaceId, $interviewId, 'ai', $this->nextQuestion($workspaceId, $iv, $questions, 0, $actorUserId));
        }

        return $this->state($workspaceId, $interviewId);
    }

    /**
     * Record the candidate's answer and advance the conversation, finalizing the
     * interview when the budget or the clock runs out.
     *
     * @return array<string, mixed>
     */
    public function answer(string $workspaceId, string $interviewId, string $text, ?string $actorUserId = null, bool $changeRequested = false): array
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

        // Never move past an unanswered question: if the candidate submits nothing
        // and did not explicitly ask for a different question, keep the SAME
        // question on screen and wait for a real answer.
        if ($text === '' && ! $changeRequested) {
            return $this->state($workspaceId, $interviewId);
        }

        if ($text !== '') {
            $this->append($workspaceId, $interviewId, 'candidate', mb_substr($text, 0, 4000));
        }

        $questions = $this->details($iv)['questions'] ?? [];
        $limit = $this->questionsLimit($iv);
        $budget = max(1, min($limit, count($questions) ?: $limit));
        $answered = $this->countAnswered($interviewId);    // questions the candidate actually ANSWERED
        $timeUp = $this->secondsRemaining($iv) <= 0;

        // Only ANSWERED questions count toward the budget — an unanswered or a
        // changed question never consumes it. A change request always yields a
        // fresh question and never ends the interview.
        if (! $changeRequested && ($answered >= $budget || $timeUp)) {
            $this->finalize($workspaceId, $interviewId, $actorUserId);
        } else {
            // Adaptive follow-up: built on the candidate's answers + CV + the job.
            $this->append($workspaceId, $interviewId, 'ai', $this->nextQuestion($workspaceId, $iv, $questions, $answered, $actorUserId));
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
            'asked' => $this->countAnswered($interviewId), // progress = answered questions
            'max_questions' => $this->questionsLimit($iv),
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

            // Auto-qualification / auto-rejection / auto-move per the job's configured
            // thresholds (the AI recommendation drives the pipeline; a human overrides).
            $this->applyAutoRules($workspaceId, $iv, (int) ($assessment['fit_score'] ?? 0), $actorUserId);
        } catch (\Throwable) {
            // Assessment + automation are best-effort.
        }
    }

    /**
     * Auto-qualify / auto-reject / auto-move based on the job's configured score
     * thresholds and the advisory fit score. Best-effort; a human can override.
     *
     * @param array<string,mixed> $iv
     */
    private function applyAutoRules(string $workspaceId, array $iv, int $fit, ?string $actorUserId): void
    {
        $appId = (string) ($iv['application_id'] ?? '');
        if ($appId === '') {
            return;
        }
        $reject = ($iv['auto_reject_score'] ?? null) !== null ? (int) $iv['auto_reject_score'] : null;
        $passing = ($iv['passing_score'] ?? null) !== null ? (int) $iv['passing_score'] : null;
        $stageId = trim((string) ($iv['auto_advance_stage_id'] ?? ''));

        try {
            if ($reject !== null && $fit < $reject) {
                $this->applications->setStatus($workspaceId, $appId, 'disqualified', $actorUserId);
            } elseif ($passing !== null && $fit >= $passing) {
                if ($stageId !== '') {
                    $this->applications->moveStage($workspaceId, $appId, $stageId, $actorUserId);
                } else {
                    $this->applications->setStatus($workspaceId, $appId, 'qualified', $actorUserId);
                }
            }
        } catch (\Throwable) {
            // Best-effort automation — never breaks interview completion.
        }
    }

    /**
     * The CV gate. Before the live AI interview begins the candidate must put a
     * CV on record — chosen from their library or freshly uploaded. Returns true
     * while no CV is recorded for this not-yet-started interview, so the room
     * shows the CV step first. An already-started or completed interview is never
     * blocked (resuming, and interviews that predate the gate, still work).
     */
    public function needsCv(string $workspaceId, string $interviewId): bool
    {
        $iv = $this->load($workspaceId, $interviewId);
        if ((string) $iv['status'] === 'completed' || ! empty($iv['started_at'])) {
            return false;
        }

        return empty($this->details($iv)['cv_ready']);
    }

    /**
     * Record the candidate's chosen/uploaded CV against this interview and open
     * the gate. The file id is one of the candidate's library CVs (entity 'cv').
     */
    public function attachCv(string $workspaceId, string $interviewId, string $cvFileId): void
    {
        $iv = $this->load($workspaceId, $interviewId);
        $details = $this->details($iv);
        $details['cv_ready'] = true;
        if ($cvFileId !== '') {
            $details['cv_file_id'] = $cvFileId;
        }
        $this->connection->statement(
            'UPDATE interviews SET details = ?, updated_at = ? WHERE id = ? AND workspace_id = ?',
            [json_encode($details), gmdate('Y-m-d H:i:s'), $interviewId, $workspaceId],
        );
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function planQuestions(string $workspaceId, string $jobId, string $jobTitle, ?string $actorUserId): array
    {
        // 1) The job's own question bank wins when the workspace has built one.
        if ($jobId !== '') {
            $bank = $this->connection->select(
                'SELECT text FROM job_questions WHERE workspace_id = ? AND job_id = ? ORDER BY position ASC, created_at ASC',
                [$workspaceId, $jobId],
            );
            if ($bank !== []) {
                return array_slice(array_map(static fn (array $r): string => (string) $r['text'], $bank), 0, self::MAX_QUESTIONS);
            }
        }

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

    /**
     * The next interviewer turn. When a real AI provider is configured this is
     * generated LIVE from the candidate's answers so far + their CV/profile + the
     * job's description, requirements and criteria — a genuine, adaptive follow-up
     * that builds on what was said. With only the offline echo provider (no usable
     * question comes back) it falls back to the planned/static question, so the
     * room still works without an AI key.
     *
     * @param  array<string,mixed>  $iv
     * @param  list<string>  $planned
     */
    private function nextQuestion(string $workspaceId, array $iv, array $planned, int $asked, ?string $actorUserId): string
    {
        $fallback = (string) ($planned[$asked] ?? ($planned !== [] ? end($planned) : "Is there anything else you'd like us to know?"));

        try {
            $remaining = max(1, min(self::MAX_QUESTIONS, max(1, count($planned))) - $asked);
            $result = $this->ai->run($workspaceId, 'interview_turn', [
                'persona' => $this->persona($workspaceId, $iv),
                'title' => (string) ($iv['job_title'] ?? 'this role'),
                'job_context' => $this->jobContext($workspaceId, $iv, $planned),
                'cv' => $this->candidateContext($workspaceId, $iv),
                'transcript' => $this->recentTranscript($workspaceId, (string) $iv['id']),
                'remaining' => $remaining,
            ], $actorUserId);

            $q = $this->cleanQuestion($result->text);
            if ($q !== '' && ! $this->alreadyAsked($workspaceId, (string) $iv['id'], $q)) {
                return $q;
            }
        } catch (\Throwable) {
            // fall through to the planned question
        }

        return $fallback;
    }

    /**
     * The interviewer's persona. The default is a strong, professional senior-HR
     * voice. When the workspace links an AI Avatar (with its own personality) to
     * the job, that personality replaces this default; removing the link restores
     * the default.
     *
     * @param  array<string,mixed>  $iv
     */
    private function persona(string $workspaceId, array $iv): string
    {
        $default = 'You are a strong, professional senior HR interviewer representing the company: composed, insightful and rigorous, yet warm and respectful. You put the candidate at ease while probing deeply and fairly.';

        try {
            $avatarId = (string) ($iv['avatar_id'] ?? '');
            if ($avatarId !== '') {
                $avatar = $this->connection->selectOne(
                    'SELECT name, persona, style_notes, prompt, greeting FROM ai_avatars WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
                    [$avatarId, $workspaceId],
                );
                if ($avatar !== null) {
                    // The avatar's personality lives in style_notes (free text), with
                    // its custom prompt and named persona as fallbacks.
                    $traits = trim((string) ($avatar['style_notes'] ?? ''));
                    if ($traits === '') {
                        $traits = trim((string) ($avatar['prompt'] ?? ''));
                    }
                    $persona = trim((string) ($avatar['persona'] ?? ''));
                    $name = trim((string) ($avatar['name'] ?? ''));
                    $who = $name !== '' ? $name : 'the interviewer';
                    if ($traits !== '' || $persona !== '') {
                        $desc = $traits !== '' ? $traits : ('a ' . $persona . ' interviewer');

                        return 'You are "' . $who . '", the company\'s AI interviewer. Stay fully in character: ' . $desc . ' Remain professional, fair and rigorous throughout.';
                    }
                }
            }
        } catch (\Throwable) {
            // No avatar link (or columns not present yet) — use the default persona.
        }

        return $default;
    }

    /**
     * The job side of the interview context: description/requirements, employment
     * details, the weighted evaluation criteria, and the question-bank topics.
     *
     * @param  array<string,mixed>  $iv
     * @param  list<string>  $planned
     */
    private function jobContext(string $workspaceId, array $iv, array $planned): string
    {
        $parts = [];
        $desc = trim((string) ($iv['job_description'] ?? ''));
        if ($desc !== '') {
            $parts[] = "Job description & requirements:\n" . mb_substr($desc, 0, 1500);
        }

        $meta = [];
        if (! empty($iv['job_employment_type'])) {
            $meta[] = (string) $iv['job_employment_type'];
        }
        if (! empty($iv['job_location'])) {
            $meta[] = (string) $iv['job_location'];
        }
        if ($meta !== []) {
            $parts[] = 'Employment: ' . implode(' · ', $meta);
        }

        $criteria = $this->connection->select(
            'SELECT label, weight FROM job_criteria WHERE workspace_id = ? AND job_id = ? ORDER BY position ASC, created_at ASC',
            [$workspaceId, (string) ($iv['job_id'] ?? '')],
        );
        if ($criteria !== []) {
            $lines = array_map(static fn (array $c): string => '- ' . (string) $c['label'] . ' (weight ' . (int) $c['weight'] . ')', $criteria);
            $parts[] = "What the hiring team is evaluating:\n" . implode("\n", $lines);
        }

        if ($planned !== []) {
            $topics = array_map(static fn ($q): string => '- ' . (string) $q, array_slice($planned, 0, self::MAX_QUESTIONS));
            $parts[] = "Topics / question bank to make sure you cover:\n" . implode("\n", $topics);
        }

        return $parts === [] ? 'A general screening interview for this role.' : implode("\n\n", $parts);
    }

    /**
     * The candidate side of the context: their stated experience and their
     * workspace CV profile (summary + structured skills/education/etc.).
     *
     * @param  array<string,mixed>  $iv
     */
    private function candidateContext(string $workspaceId, array $iv): string
    {
        $parts = ['Name: ' . (string) ($iv['candidate_name'] ?? 'the candidate') . '.'];
        if (! empty($iv['candidate_experience'])) {
            $parts[] = 'Stated experience: ' . (int) $iv['candidate_experience'] . ' year(s).';
        }

        $prof = $this->connection->selectOne(
            'SELECT summary, details FROM candidate_profiles WHERE workspace_id = ? AND user_id = ?',
            [$workspaceId, (string) ($iv['candidate_user_id'] ?? '')],
        );
        if ($prof !== null) {
            $summary = trim((string) ($prof['summary'] ?? ''));
            if ($summary !== '') {
                $parts[] = 'CV summary: ' . mb_substr($summary, 0, 900);
            }
            $details = $prof['details'] ?? null;
            $details = is_array($details) ? $details : (is_string($details) ? (json_decode($details, true) ?: []) : []);
            foreach (['skills' => 'Skills', 'education' => 'Education', 'certifications' => 'Certifications', 'languages' => 'Languages', 'location' => 'Location'] as $key => $label) {
                $v = trim((string) ($details[$key] ?? ''));
                if ($v !== '') {
                    $parts[] = $label . ': ' . mb_substr($v, 0, 300);
                }
            }
        }

        return implode("\n", $parts);
    }

    /** The recent conversation, oldest→newest, bounded so the prompt stays small. */
    private function recentTranscript(string $workspaceId, string $interviewId): string
    {
        $rows = $this->connection->select(
            'SELECT role, content FROM interview_messages WHERE interview_id = ? AND workspace_id = ? ORDER BY position ASC',
            [$interviewId, $workspaceId],
        );
        $rows = array_slice($rows, -16);
        $lines = [];
        foreach ($rows as $r) {
            $who = (string) $r['role'] === 'candidate' ? 'Candidate' : 'Interviewer';
            $lines[] = $who . ': ' . mb_substr((string) $r['content'], 0, 700);
        }

        return $lines === [] ? '(the interview is just starting)' : implode("\n", $lines);
    }

    /** Pull the first real question out of a model reply (strip numbering/preamble). */
    private function cleanQuestion(string $text): string
    {
        foreach (preg_split('/\r?\n/', trim($text)) ?: [] as $line) {
            $line = trim((string) preg_replace('/^\s*(?:\d+[\.\)]|[-*•])\s*/', '', $line));
            if (mb_strlen($line) >= 8 && str_contains($line, '?')) {
                return mb_substr($line, 0, 500);
            }
        }

        return '';
    }

    /** Guard against the model repeating a question already posed. */
    private function alreadyAsked(string $workspaceId, string $interviewId, string $question): bool
    {
        $norm = mb_strtolower(trim($question));
        $rows = $this->connection->select(
            "SELECT content FROM interview_messages WHERE interview_id = ? AND workspace_id = ? AND role = 'ai'",
            [$interviewId, $workspaceId],
        );
        foreach ($rows as $r) {
            if (mb_strtolower(trim((string) $r['content'])) === $norm) {
                return true;
            }
        }

        return false;
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

    /** Questions the candidate actually ANSWERED — the only thing that consumes the budget. */
    private function countAnswered(string $interviewId): int
    {
        return (int) ($this->connection->selectOne("SELECT COUNT(*) AS c FROM interview_messages WHERE interview_id = ? AND role = 'candidate'", [$interviewId])['c'] ?? 0);
    }

    private function secondsRemaining(array $iv): int
    {
        $minutes = $this->durationMinutes($iv);
        if (empty($iv['started_at'])) {
            return $minutes * 60;
        }
        $elapsed = time() - strtotime((string) $iv['started_at'] . ' UTC');

        return max(0, $minutes * 60 - $elapsed);
    }

    /** The interview duration in minutes — the job override, else the default. */
    private function durationMinutes(array $iv): int
    {
        $override = (int) ($iv['interview_duration_minutes'] ?? 0);

        return $override > 0 ? min(180, $override) : self::DURATION_MINUTES;
    }

    /** The question budget — the job override, else the default. */
    private function questionsLimit(array $iv): int
    {
        $override = (int) ($iv['questions_limit'] ?? 0);

        return $override > 0 ? min(30, $override) : self::MAX_QUESTIONS;
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
            'SELECT i.*, u.name AS candidate_name, u.years_experience AS candidate_experience,
                    j.title AS job_title, j.description AS job_description,
                    j.employment_type AS job_employment_type, j.location AS job_location,
                    j.avatar_id AS avatar_id, j.interview_duration_minutes, j.questions_limit,
                    j.passing_score, j.auto_reject_score, j.auto_advance_stage_id,
                    j.interview_expiration_days, j.max_attempts, j.deadline_at AS job_deadline_at
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
