<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Learning\Domain\QuizGrader;
use HaHireAI\Shared\Ulid;

/**
 * Quiz authoring (questions + options) and grading/attempts for the Learning
 * module. Grading is the pure {@see QuizGrader} (zero AI). Tenant-scoped.
 */
final class QuizService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function addQuestion(string $workspaceId, string $itemId, string $question, string $type = 'single'): string
    {
        $type = in_array($type, ['single', 'multiple', 'boolean'], true) ? $type : 'single';
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO learning_quiz_questions (id, workspace_id, item_id, question, type, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $itemId, mb_substr(trim($question), 0, 1000) ?: 'Question', $type, $this->nextPosition('learning_quiz_questions', 'item_id', $itemId, $workspaceId), gmdate('Y-m-d H:i:s')],
        );

        return $id;
    }

    public function addOption(string $workspaceId, string $questionId, string $label, bool $isCorrect): string
    {
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO learning_quiz_options (id, workspace_id, question_id, label, is_correct, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $questionId, mb_substr(trim($label), 0, 500) ?: 'Option', $isCorrect ? 1 : 0, $this->nextPosition('learning_quiz_options', 'question_id', $questionId, $workspaceId), gmdate('Y-m-d H:i:s')],
        );

        return $id;
    }

    public function deleteQuestion(string $workspaceId, string $questionId): bool
    {
        return $this->connection->statement(
            'DELETE FROM learning_quiz_questions WHERE id = ? AND workspace_id = ?',
            [$questionId, $workspaceId],
        ) > 0;
    }

    /**
     * Questions (ordered) for a quiz item, each with its options.
     *
     * @return list<array<string, mixed>>
     */
    public function questionsFor(string $workspaceId, string $itemId): array
    {
        $questions = $this->connection->select(
            'SELECT * FROM learning_quiz_questions WHERE item_id = ? AND workspace_id = ? ORDER BY position, created_at',
            [$itemId, $workspaceId],
        );
        if ($questions === []) {
            return [];
        }
        $ids = array_map(static fn (array $q): string => (string) $q['id'], $questions);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $options = $this->connection->select(
            "SELECT * FROM learning_quiz_options WHERE workspace_id = ? AND question_id IN ({$place}) ORDER BY position",
            array_merge([$workspaceId], $ids),
        );
        $byQ = [];
        foreach ($options as $o) {
            $byQ[(string) $o['question_id']][] = $o;
        }
        foreach ($questions as &$q) {
            $q['options'] = $byQ[(string) $q['id']] ?? [];
        }

        return $questions;
    }

    /**
     * Grade a submission, record the attempt + answers, and return the result.
     *
     * @param  array<string, list<string>>  $answers  question_id => selected option ids
     * @return array{score: int, max: int, percent: int, passed: bool, attempt_id: string}
     */
    public function submit(string $workspaceId, string $programId, string $itemId, string $userId, array $answers, int $passMark = 70): array
    {
        $questions = $this->questionsFor($workspaceId, $itemId);
        $normalised = array_map(
            static fn (array $q): array => [
                'id' => (string) $q['id'],
                'type' => (string) ($q['type'] ?? 'single'),
                'options' => array_map(
                    static fn (array $o): array => ['id' => (string) $o['id'], 'is_correct' => (int) $o['is_correct'] === 1],
                    (array) $q['options'],
                ),
            ],
            $questions,
        );

        $graded = QuizGrader::grade($normalised, $answers);
        $passed = $graded['max'] > 0 && $graded['percent'] >= max(1, min(100, $passMark));

        $attemptId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO learning_quiz_attempts (id, workspace_id, program_id, item_id, user_id, score, max_score, percent, passed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$attemptId, $workspaceId, $programId, $itemId, $userId, $graded['score'], $graded['max'], $graded['percent'], $passed ? 1 : 0, $now],
        );
        foreach ($graded['results'] as $qid => $res) {
            $selected = $res['selected'] !== [] ? $res['selected'][0] : null;
            $this->connection->statement(
                'INSERT INTO learning_quiz_answers (id, workspace_id, attempt_id, question_id, option_id, is_correct, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $attemptId, $qid, $selected, $res['correct'] ? 1 : 0, $now],
            );
        }

        return ['score' => $graded['score'], 'max' => $graded['max'], 'percent' => $graded['percent'], 'passed' => $passed, 'attempt_id' => $attemptId];
    }

    /** @return array<string,mixed>|null the learner's best attempt on a quiz item */
    public function bestAttempt(string $workspaceId, string $itemId, string $userId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM learning_quiz_attempts WHERE workspace_id = ? AND item_id = ? AND user_id = ? ORDER BY percent DESC, created_at DESC LIMIT 1',
            [$workspaceId, $itemId, $userId],
        );
    }

    private function nextPosition(string $table, string $column, string $value, string $workspaceId): int
    {
        return \HaHireAI\Support\Position::next($this->connection, $table, [$column => $value, 'workspace_id' => $workspaceId]);
    }
}
