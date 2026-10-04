<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Collects PUBLIC DEV.to data via the official, unauthenticated API
 * (dev.to/api/articles?username=X) — a stable, legal source. A developer's public
 * articles and their tags are a strong, job-relevant technical signal (the tags
 * are technologies/topics). Builds skills + relevance text from the tags/titles.
 *
 * Resilient: any non-2xx / network failure → an unreachable snapshot (neutral).
 */
final class DevToAdapter implements SocialAdapter
{
    public function __construct(private readonly HttpFetcher $http)
    {
    }

    public function key(): string
    {
        return 'devto';
    }

    public function platform(): string
    {
        return 'devto';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#^https?://(www\.)?dev\.to/#i', $url);
    }

    public function fetch(string $url): array
    {
        $user = $this->username($url);
        if ($user === null) {
            return SnapshotEnvelope::unreachable('devto', 'no username in url');
        }

        $res = $this->http->get("https://dev.to/api/articles?username={$user}&per_page=30");
        if (! $res->ok()) {
            return SnapshotEnvelope::unreachable('devto', $res->error ?? ('http ' . $res->status));
        }
        $articles = (array) ($res->json() ?? []);
        if ($articles === []) {
            // Reachable but no public articles → neutral, not a penalty.
            return (new SnapshotEnvelope('devto'))->reachable()->summary('dev.to: no public articles')->toArray();
        }

        $env = (new SnapshotEnvelope('devto'))->reachable()->fetched();
        $reactions = 0;
        $titles = [];
        $tags = [];
        foreach ($articles as $a) {
            if (! is_array($a)) {
                continue;
            }
            $reactions += (int) ($a['positive_reactions_count'] ?? 0);
            if (! empty($a['title'])) {
                $titles[] = (string) $a['title'];
            }
            foreach ((array) ($a['tag_list'] ?? []) as $tag) {
                $tags[(string) $tag] = true;
            }
        }

        $env->addSkills(array_keys($tags))
            ->text(implode(' ', array_slice($titles, 0, 30)))
            ->text(implode(' ', array_keys($tags)))
            ->signal('articles', null, count($titles))
            ->signal('total_reactions', null, $reactions)
            ->signal('top_tags', implode(', ', array_slice(array_keys($tags), 0, 8)) ?: null);

        $footprint = (int) round(
            0.6 * SnapshotEnvelope::logScale(count($titles), 25)
            + 0.4 * SnapshotEnvelope::logScale($reactions, 800)
        );
        $env->footprint($footprint)
            ->summary(sprintf('dev.to: %d articles, %d reactions%s', count($titles), $reactions, $tags !== [] ? ' · ' . implode('/', array_slice(array_keys($tags), 0, 4)) : ''));

        return $env->toArray();
    }

    private function username(string $url): ?string
    {
        if (! preg_match('#dev\.to/([A-Za-z0-9_\-]+)#i', $url, $m)) {
            return null;
        }
        $reserved = ['tags', 'top', 'latest', 'about', 'contact', 'search', 'settings', 'enter', 'dashboard'];

        return in_array(strtolower($m[1]), $reserved, true) ? null : $m[1];
    }
}
