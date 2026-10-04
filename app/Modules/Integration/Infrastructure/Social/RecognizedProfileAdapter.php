<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Handles platforms whose profiles are login-walled or actively block automated
 * reads (LinkedIn, X/Twitter, Facebook, Instagram, TikTok). We deliberately do
 * NOT scrape them — that would be unstable and against their terms. Instead we
 * RECOGNISE the platform and the username from the URL and record a NEUTRAL
 * snapshot: the link is acknowledged on the candidate's profile, but it carries
 * no extractable signal, so it contributes exactly ZERO to the social score
 * (never a penalty). When an official API for one of these is added later, it
 * simply becomes a richer adapter ahead of this one.
 */
final class RecognizedProfileAdapter implements SocialAdapter
{
    /** host needle => canonical platform name. */
    private const PLATFORMS = [
        'linkedin.' => 'linkedin',
        'twitter.' => 'twitter',
        'x.com' => 'twitter',
        'facebook.' => 'facebook',
        'instagram.' => 'instagram',
        'tiktok.' => 'tiktok',
    ];

    public function key(): string
    {
        return 'recognized';
    }

    public function platform(): string
    {
        return 'social';
    }

    public function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (array_keys(self::PLATFORMS) as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function fetch(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $platform = 'social';
        foreach (self::PLATFORMS as $needle => $name) {
            if (str_contains($host, $needle)) {
                $platform = $name;
                break;
            }
        }

        // Recognised but intentionally not fetched → reachable=false, footprint=null
        // → neutral (excluded from the social average).
        $env = (new SnapshotEnvelope($platform))
            ->summary(ucfirst($platform) . ' profile (recorded; not auto-analysed)')
            ->signal('recognized', $platform);

        $username = $this->username($url);
        if ($username !== null) {
            $env->signal('username', $username);
        }

        return $env->toArray();
    }

    private function username(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }
        $parts = explode('/', $path);
        // linkedin.com/in/<user>  → take the segment after "in"; else first segment.
        if (($parts[0] ?? '') === 'in' && isset($parts[1])) {
            return $parts[1];
        }

        return ltrim($parts[0] ?? '', '@') ?: null;
    }
}
