<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

/**
 * Pure helpers for the candidate's social links: detect the platform from a URL
 * and normalise/validate a raw link. Used when saving the GLOBAL social profiles
 * and when rendering them. No I/O.
 */
final class SocialLink
{
    /** host needle => canonical platform label. */
    private const PLATFORMS = [
        'linkedin.' => 'linkedin',
        'github.' => 'github',
        'gitlab.' => 'gitlab',
        'stackoverflow.' => 'stackoverflow',
        'kaggle.' => 'kaggle',
        'behance.' => 'behance',
        'dribbble.' => 'dribbble',
        'medium.' => 'medium',
        'dev.to' => 'devto',
        'scholar.google' => 'scholar',
        'twitter.' => 'twitter',
        'x.com' => 'twitter',
        'facebook.' => 'facebook',
        'instagram.' => 'instagram',
    ];

    /** The fields the Preparation screen offers (label => placeholder host). */
    public const FIELDS = [
        'linkedin' => 'LinkedIn',
        'github' => 'GitHub',
        'portfolio' => 'Portfolio / Website',
        'behance' => 'Behance',
        'dribbble' => 'Dribbble',
        'stackoverflow' => 'StackOverflow',
        'medium' => 'Medium',
        'twitter' => 'Twitter / X',
    ];

    public static function platformFor(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return 'website';
        }
        foreach (self::PLATFORMS as $needle => $name) {
            if (str_contains($host, $needle)) {
                return $name;
            }
        }

        return 'website';
    }

    /** Normalise a user-entered link (adds https://, trims), or null if unusable. */
    public static function normalize(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        $host = parse_url($raw, PHP_URL_HOST);
        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        return mb_substr($raw, 0, 480);
    }
}
