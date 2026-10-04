<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialLink;
use HaHireAI\Shared\Ulid;

/**
 * The candidate's GLOBAL social links — owned by the User, reused across every
 * workspace/application and auto-filled on the Preparation screen. Saving is an
 * idempotent upsert per (user, url); platform is detected automatically.
 */
final class UserSocialProfileService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> the user's saved links */
    public function list(string $userId): array
    {
        return $this->connection->select(
            'SELECT id, platform, url, username, created_at FROM user_social_profiles WHERE user_id = ? ORDER BY created_at ASC',
            [$userId],
        );
    }

    /** @return list<string> just the URLs (for the probe) */
    public function urls(string $userId): array
    {
        return array_map(static fn (array $r): string => (string) $r['url'], $this->list($userId));
    }

    /**
     * Upsert the given links onto the user's profile (auto-fill source of truth).
     * Existing links are kept; new ones added; blanks ignored. Returns the saved
     * URL list.
     *
     * @param  list<string>  $rawLinks
     * @return list<string>
     */
    public function save(string $userId, array $rawLinks): array
    {
        $existing = [];
        foreach ($this->list($userId) as $row) {
            $existing[(string) $row['url']] = true;
        }

        $now = gmdate('Y-m-d H:i:s');
        foreach ($rawLinks as $raw) {
            $url = SocialLink::normalize((string) $raw);
            if ($url === null || isset($existing[$url])) {
                continue;
            }
            $existing[$url] = true;
            $this->connection->statement(
                'INSERT INTO user_social_profiles (id, user_id, platform, url, username, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $userId, SocialLink::platformFor($url), $url, $this->usernameFrom($url), $now, $now],
            );
        }

        return array_keys($existing);
    }

    public function remove(string $userId, string $id): void
    {
        $this->connection->statement('DELETE FROM user_social_profiles WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    private function usernameFrom(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }
        $parts = explode('/', $path);
        if (($parts[0] ?? '') === 'in' && isset($parts[1])) {
            return mb_substr($parts[1], 0, 180);
        }

        return mb_substr(ltrim($parts[0] ?? '', '@'), 0, 180) ?: null;
    }
}
