<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Domain;

/**
 * Pure, deterministic quiz grading — ZERO AI. Each question is worth one point and
 * is correct when the learner's selected option set exactly matches the question's
 * correct-option set (so single, boolean and multiple-select all work). No I/O.
 */
final class QuizGrader
{
    /**
     * @param  list<array{id: string, type?: string, options: list<array{id: string, is_correct: bool}>}>  $questions
     * @param  array<string, list<string>>  $answers  question_id => selected option ids
     * @return array{score: int, max: int, percent: int, results: array<string, array{correct: bool, selected: list<string>}>}
     */
    public static function grade(array $questions, array $answers): array
    {
        $score = 0;
        $max = 0;
        $results = [];

        foreach ($questions as $q) {
            $qid = (string) $q['id'];
            $correctSet = [];
            foreach ($q['options'] as $opt) {
                if ($opt['is_correct']) {
                    $correctSet[] = (string) $opt['id'];
                }
            }
            // A question with no correct option marked is ungradable — skip it.
            if ($correctSet === []) {
                $results[$qid] = ['correct' => false, 'selected' => $answers[$qid] ?? []];

                continue;
            }
            $max++;
            $selected = array_values(array_unique($answers[$qid] ?? []));
            sort($selected);
            sort($correctSet);
            $isCorrect = $selected === $correctSet;
            if ($isCorrect) {
                $score++;
            }
            $results[$qid] = ['correct' => $isCorrect, 'selected' => $selected];
        }

        $percent = $max > 0 ? (int) round($score / $max * 100) : 0;

        return ['score' => $score, 'max' => $max, 'percent' => $percent, 'results' => $results];
    }
}
