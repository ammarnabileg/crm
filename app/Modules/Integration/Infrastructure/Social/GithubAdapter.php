<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Collects PUBLIC GitHub data via the official, unauthenticated REST API
 * (api.github.com) — a stable, legal source. Reads the profile (bio, followers,
 * public repos, account age) and the most recently pushed repositories
 * (languages, names, topics, stars) to build a technical footprint and the
 * relevance text the scoring engine matches against the job.
 *
 * Resilient: any non-2xx / network failure → an unreachable snapshot (neutral).
 */
final class GithubAdapter implements SocialAdapter
{
    public function __construct(private readonly HttpFetcher $http)
    {
    }

    public function key(): string
    {
        return 'github';
    }

    public function platform(): string
    {
        return 'github';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#^https?://(www\.)?github\.com/#i', $url);
    }

    public function fetch(string $url): array
    {
        $user = $this->username($url);
        if ($user === null) {
            return SnapshotEnvelope::unreachable('github', 'no username in url');
        }

        $profileRes = $this->http->get("https://api.github.com/users/{$user}");
        if (! $profileRes->ok()) {
            return SnapshotEnvelope::unreachable('github', $profileRes->error ?? ('http ' . $profileRes->status));
        }
        $profile = $profileRes->json();
        if ($profile === null || ! isset($profile['login'])) {
            return SnapshotEnvelope::unreachable('github', 'unexpected response');
        }

        $env = (new SnapshotEnvelope('github'))->reachable()->fetched();

        $repos = (int) ($profile['public_repos'] ?? 0);
        $followers = (int) ($profile['followers'] ?? 0);
        $bio = trim((string) ($profile['bio'] ?? ''));
        $company = trim((string) ($profile['company'] ?? ''));
        $ageDays = $this->accountAgeDays((string) ($profile['created_at'] ?? ''));

        $env->signal('public_repos', null, $repos)
            ->signal('followers', null, $followers)
            ->signal('following', null, (int) ($profile['following'] ?? 0))
            ->text($bio)->text($company)
            ->signal('bio', $bio !== '' ? $bio : null);
        if ($ageDays !== null) {
            $env->signal('account_age_days', null, $ageDays);
        }

        // Recent repositories → languages, names, topics, total stars.
        $reposRes = $this->http->get("https://api.github.com/users/{$user}/repos?sort=pushed&per_page=20", [
            'Accept' => 'application/vnd.github.mercy-preview+json',
        ]);
        $totalStars = 0;
        $languages = [];
        $repoNames = [];
        $topics = [];
        if ($reposRes->ok()) {
            foreach ((array) ($reposRes->json() ?? []) as $repo) {
                if (! is_array($repo) || ($repo['fork'] ?? false) === true) {
                    continue;
                }
                $totalStars += (int) ($repo['stargazers_count'] ?? 0);
                if (! empty($repo['language'])) {
                    $languages[(string) $repo['language']] = true;
                }
                if (! empty($repo['name'])) {
                    $repoNames[] = (string) $repo['name'];
                }
                foreach ((array) ($repo['topics'] ?? []) as $topic) {
                    $topics[(string) $topic] = true;
                }
            }
        }

        $langs = array_keys($languages);
        $env->addSkills($langs)->addSkills(array_keys($topics))
            ->text(implode(' ', array_slice($repoNames, 0, 20)))
            ->text(implode(' ', $langs))
            ->text(implode(' ', array_keys($topics)))
            ->signal('total_stars', null, $totalStars)
            ->signal('top_languages', implode(', ', array_slice($langs, 0, 8)) ?: null);

        // Footprint magnitude: a blend of repos, followers and stars (log-scaled).
        $footprint = (int) round(
            0.45 * SnapshotEnvelope::logScale($repos, 60)
            + 0.30 * SnapshotEnvelope::logScale($followers, 500)
            + 0.25 * SnapshotEnvelope::logScale($totalStars, 1000)
        );
        $env->footprint($footprint)
            ->summary(sprintf('GitHub: %d repos, %d followers, %d stars%s', $repos, $followers, $totalStars, $langs !== [] ? ' · ' . implode('/', array_slice($langs, 0, 4)) : ''));

        return $env->toArray();
    }

    private function username(string $url): ?string
    {
        if (! preg_match('#github\.com/([A-Za-z0-9\-]+)#i', $url, $m)) {
            return null;
        }
        $reserved = ['orgs', 'about', 'features', 'topics', 'collections', 'trending', 'marketplace', 'sponsors', 'settings'];

        return in_array(strtolower($m[1]), $reserved, true) ? null : $m[1];
    }

    private function accountAgeDays(string $createdAt): ?int
    {
        if ($createdAt === '') {
            return null;
        }
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return null;
        }

        return (int) max(0, floor((time() - $ts) / 86400));
    }
}
