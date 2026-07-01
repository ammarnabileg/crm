<?php

declare(strict_types=1);

namespace HaHireAI\Support;

/**
 * Canonical filename sanitiser for stored uploads (files module, CV library, …).
 * Pure: keeps only [A-Za-z0-9._-], collapses everything else to '_', trims stray
 * underscores and caps the length. The single source shared by every storer so
 * the copies can never drift.
 */
final class Filename
{
    public static function safe(string $name, string $fallback = 'file'): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?? $fallback;

        return substr(trim($clean, '_'), 0, 120) ?: $fallback;
    }
}
