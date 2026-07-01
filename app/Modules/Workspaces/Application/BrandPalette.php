<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Application;

/**
 * Turns a single brand hex colour into the full --brand-* scale the UI themes on,
 * so a workspace can white-label the entire app with its own accent. Pure maths —
 * no I/O. The app's Tailwind maps every `indigo-*` utility to `rgb(var(--brand-N))`,
 * so overriding these channels on #app-shell recolours the whole subtree.
 */
final class BrandPalette
{
    /** Tint (mix toward white) / shade (mix toward black) ratio per Tailwind step. */
    private const STEPS = [
        50 => 0.95, 100 => 0.90, 200 => 0.75, 300 => 0.55, 400 => 0.30,
        500 => 0.13, 600 => 0.0, 700 => -0.15, 800 => -0.30, 900 => -0.45, 950 => -0.55,
    ];

    public static function isValidHex(string $hex): bool
    {
        return preg_match('/^#?[0-9a-fA-F]{6}$/', trim($hex)) === 1;
    }

    /**
     * Inline `style` value setting every --brand-* channel from a base hex, or '' if
     * the hex is invalid. Channels are space-separated RGB (Tailwind alpha-aware).
     */
    public static function styleVars(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (! self::isValidHex($hex)) {
            return '';
        }

        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $parts = [];
        foreach (self::STEPS as $step => $ratio) {
            [$cr, $cg, $cb] = self::mix($r, $g, $b, $ratio);
            $parts[] = "--brand-{$step}: {$cr} {$cg} {$cb}";
        }

        return implode('; ', $parts) . ';';
    }

    /**
     * Mix a colour toward white (ratio > 0) or black (ratio < 0).
     *
     * @return array{0:int,1:int,2:int}
     */
    private static function mix(int $r, int $g, int $b, float $ratio): array
    {
        if ($ratio >= 0) {
            return [
                (int) round($r + (255 - $r) * $ratio),
                (int) round($g + (255 - $g) * $ratio),
                (int) round($b + (255 - $b) * $ratio),
            ];
        }
        $k = 1 + $ratio; // ratio is negative → darken

        return [(int) round($r * $k), (int) round($g * $k), (int) round($b * $k)];
    }
}
