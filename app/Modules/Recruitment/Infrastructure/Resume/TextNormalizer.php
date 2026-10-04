<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Infrastructure\Resume;

/**
 * Shared text normalisation used by every parser so the Analysis Engine always
 * receives clean, UTF-8, consistently-spaced text regardless of the source
 * format. Pure, dependency-free.
 */
final class TextNormalizer
{
    public static function normalize(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        // Force valid UTF-8 (drops invalid byte sequences from odd encodings).
        if (function_exists('mb_convert_encoding')) {
            $detected = mb_detect_encoding($raw, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'UTF-8';
            $raw = mb_convert_encoding($raw, 'UTF-8', $detected);
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        // Common PDF/Doc ligatures & smart punctuation → ASCII-ish equivalents.
        $raw = strtr($raw, [
            "\u{00A0}" => ' ', "\u{2019}" => "'", "\u{2018}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{2013}" => '-', "\u{2014}" => '-',
            "\u{2022}" => ' ', "\u{FB01}" => 'fi', "\u{FB02}" => 'fl', "\u{0000}" => '',
        ]);

        // Collapse runs of spaces/tabs but preserve line structure.
        $raw = preg_replace('/[ \t\x0B\f]+/', ' ', $raw) ?? $raw;
        $raw = preg_replace('/\n{3,}/', "\n\n", $raw) ?? $raw;
        $raw = preg_replace('/ *\n */', "\n", $raw) ?? $raw;

        return trim($raw);
    }
}
