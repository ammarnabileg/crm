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
