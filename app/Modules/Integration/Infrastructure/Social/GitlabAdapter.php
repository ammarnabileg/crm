<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Collects PUBLIC GitLab data via the official, unauthenticated REST API
 * (gitlab.com/api/v4) — a stable, legal source. Reads the public profile and the
 * user's public projects (names, descriptions, topics, stars) to build a
 * technical footprint + the relevance text the scoring engine matches to the job.
 *
 * Resilient: any non-2xx / network failure → an unreachable snapshot (neutral).
 * Adding this source required NO change to Recruitment or the scorer — only this
 * class + one registry line (the whole point of the adapter architecture).
 */
final class GitlabAdapter implements SocialAdapter
{
    public function __construct(private readonly HttpFetcher $http)
    {
    }

    public function key(): string
    {
        return 'gitlab';
    }

    public function platform(): string
    {
        return 'gitlab';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#^https?://(www\.)?gitlab\.com/#i', $url);
    }

    public function fetch(string $url): array
    {
        $user = $this->username($url);
        if ($user === null) {
            return SnapshotEnvelope::unreachable('gitlab', 'no username in url');
        }

        $usersRes = $this->http->get("https://gitlab.com/api/v4/users?username={$user}");
        if (! $usersRes->ok()) {
            return SnapshotEnvelope::unreachable('gitlab', $usersRes->error ?? ('http ' . $usersRes->status));
        }
        $users = (array) ($usersRes->json() ?? []);
        $profile = $users[0] ?? null;
        if (! is_array($profile) || ! isset($profile['id'])) {
            return SnapshotEnvelope::unreachable('gitlab', 'user not found');
        }

        $env = (new SnapshotEnvelope('gitlab'))->reachable()->fetched();
        $bio = trim((string) ($profile['bio'] ?? ''));
        $env->text($bio)->text(trim((string) ($profile['organization'] ?? '')))
            ->signal('bio', $bio !== '' ? $bio : null);
        $ageDays = $this->accountAgeDays((string) ($profile['created_at'] ?? ''));
        if ($ageDays !== null) {
            $env->signal('account_age_days', null, $ageDays);
        }

        $projRes = $this->http->get("https://gitlab.com/api/v4/users/{$profile['id']}/projects?order_by=last_activity_at&per_page=20");
        $totalStars = 0;
        $names = [];
        $topics = [];
        $count = 0;
        if ($projRes->ok()) {
            foreach ((array) ($projRes->json() ?? []) as $proj) {
                if (! is_array($proj)) {
                    continue;
                }
                $count++;
                $totalStars += (int) ($proj['star_count'] ?? 0);
                if (! empty($proj['name'])) {
                    $names[] = (string) $proj['name'];
                }
                $env->text(trim((string) ($proj['description'] ?? '')));
                foreach ((array) ($proj['topics'] ?? $proj['tag_list'] ?? []) as $topic) {
                    $topics[(string) $topic] = true;
                }
            }
        }

        $env->addSkills(array_keys($topics))
            ->text(implode(' ', array_slice($names, 0, 20)))
            ->text(implode(' ', array_keys($topics)))
            ->signal('public_projects', null, $count)
            ->signal('total_stars', null, $totalStars)
            ->signal('top_topics', implode(', ', array_slice(array_keys($topics), 0, 8)) ?: null);

        $footprint = (int) round(
            0.55 * SnapshotEnvelope::logScale($count, 40)
            + 0.45 * SnapshotEnvelope::logScale($totalStars, 500)
        );
        $env->footprint($footprint)
            ->summary(sprintf('GitLab: %d projects, %d stars%s', $count, $totalStars, $topics !== [] ? ' · ' . implode('/', array_slice(array_keys($topics), 0, 4)) : ''));

        return $env->toArray();
    }

    private function username(string $url): ?string
    {
        if (! preg_match('#gitlab\.com/([A-Za-z0-9_.\-]+)#i', $url, $m)) {
            return null;
        }
        $reserved = ['explore', 'help', 'users', 'groups', 'dashboard', 'projects', '-'];

        return in_array(strtolower($m[1]), $reserved, true) ? null : $m[1];
    }

    private function accountAgeDays(string $createdAt): ?int
    {
        if ($createdAt === '') {
            return null;
        }
        $ts = strtotime($createdAt);

        return $ts === false ? null : (int) max(0, floor((time() - $ts) / 86400));
    }
}
