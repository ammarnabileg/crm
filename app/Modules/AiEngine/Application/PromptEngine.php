<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Versioned, variable-driven prompt rendering. Prompts are data, never written
 * inside services (docs/PROMPT_ENGINE.md, docs/PROMPT_VERSIONING.md).
 */
final class PromptEngine
{
    private const DEFAULTS = [
        'summarize_candidate' => "Summarize this candidate for the hiring team.\nName: {{name}}\nEmail: {{email}}\nApplications: {{applications}}\nNotes: {{notes}}\nProvide strengths, gaps, and a one-line recommendation.",
        'generate_job_description' => "Write a concise, inclusive job description.\nTitle: {{title}}\nLocation: {{location}}\nInclude responsibilities, requirements, and what success looks like.",
        'candidate_recommendation' => "Given candidate {{name}} for the role {{title}}, provide a hiring recommendation (advance / hold / reject) with a short rationale.",
        'ai_interview' => "Conduct a structured screening interview for {{name}} applying to {{title}}.\nContext: {{notes}}\nAsk role-relevant questions, evaluate the answers, then end with: a 2-3 sentence summary, a SCORE from 0-100, and a recommendation (advance / hold / reject).",
        'interview_questions' => "Generate 6 role-specific interview questions for the position {{title}}. Cover technical depth, problem-solving, and collaboration.",
        'interview_turn' => "You are a sharp, warm expert interviewer running a live screening interview for the role: {{title}}.\nRole focus / topics to cover:\n{{job_context}}\nCandidate background:\n{{cv}}\n\nInterview so far (oldest first, newest last):\n{{transcript}}\n\nAsk the SINGLE best next question. Rules:\n- Build DIRECTLY on the candidate's most recent answer: probe for specifics, a concrete example, numbers, the decision they made, or a trade-off. If the last answer was vague, generic, or evasive, politely press for the missing detail instead of moving on.\n- Never repeat or merely rephrase a question already asked above.\n- Over the whole interview make sure the role's focus topics get covered; you have about {{remaining}} question(s) left, so prioritise.\n- Sound human and conversational, one question only, no preamble, no feedback on their answer, no numbering.\nReturn ONLY the question text (a single sentence ending in a question mark).",
        'assess_candidate' => "Assess candidate {{name}} for {{title}} from this interview transcript.\n{{transcript}}\nScore the standard competencies (0-100), infer behaviour (DISC, Big Five), flag risks with severity, and give an overall fit score with a recommendation. Advisory only — a human decides.",
        'analyze_cv' => "Analyze this CV for {{title}}: extract skills, past companies, years of experience, gaps, and a CV-to-role match score (0-100).\n{{cv_text}}",
        'compare_candidates' => "Compare these candidates and answer the question.\nCandidates:\n{{candidates}}\nQuestion: {{question}}\nGive a concise, evidence-based answer naming the best fit. Advisory only — a human decides.",
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string, scalar|null> $variables */
    public function render(string $capability, array $variables = [], string $locale = 'en'): string
    {
        $template = $this->template($capability, $locale);

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function (array $m) use ($variables): string {
            return (string) ($variables[$m[1]] ?? '');
        }, $template) ?? $template;
    }

    public function seedDefaults(): int
    {
        $count = 0;
        $now = gmdate('Y-m-d H:i:s');

        foreach (self::DEFAULTS as $capability => $template) {
            $exists = $this->connection->selectOne(
                'SELECT id FROM prompt_templates WHERE capability = ? AND locale = ? ORDER BY version DESC LIMIT 1',
                [$capability, 'en'],
            );
            if ($exists !== null) {
                continue;
            }
            $this->connection->statement(
                'INSERT INTO prompt_templates (id, capability, version, locale, template, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $capability, 1, 'en', $template, $now, $now],
            );
            $count++;
        }

        return $count;
    }

    private function template(string $capability, string $locale): string
    {
        $row = $this->connection->selectOne(
            'SELECT template FROM prompt_templates WHERE capability = ? AND locale = ? ORDER BY version DESC LIMIT 1',
            [$capability, $locale],
        );

        return $row['template'] ?? (self::DEFAULTS[$capability] ?? 'Context: {{context}}');
    }
}
