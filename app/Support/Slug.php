<?php

declare(strict_types=1);

namespace HaHireAI\Support;

/**
 * The one canonical slug base used across the platform (jobs, programs, learning
 * paths, …). Pure and deterministic: lowercases, collapses every run of
 * non-alphanumerics to a single hyphen, and trims stray hyphens. Callers add
 * their own uniqueness strategy (random suffix, taken-check) and fallback — this
 * only owns the shared transformation so the copies can never drift.
 */
final class Slug
{
    /**
     * @param  int  $maxLength  0 = no cap; otherwise truncate the result.
     */
    public static function make(string $text, int $maxLength = 0): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($text)), '-');

        return $maxLength > 0 ? mb_substr($slug, 0, $maxLength) : $slug;
    }
}
