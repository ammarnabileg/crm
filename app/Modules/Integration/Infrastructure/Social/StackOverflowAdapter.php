<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Collects PUBLIC StackOverflow data via the official StackExchange API — a
 * stable, legal source. Reads reputation, badge counts and the user's top tags
 * (which double as skills + relevance text). Resilient: failures → neutral.
 */
final class StackOverflowAdapter implements SocialAdapter
{
    public function __construct(private readonly HttpFetcher $http)
    {
    }

    public function key(): string
    {
        return 'stackoverflow';
    }

    public function platform(): string
    {
        return 'stackoverflow';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#^https?://(www\.)?stackoverflow\.com/users/\d+#i', $url);
    }

    public function fetch(string $url): array
    {
        if (! preg_match('#stackoverflow\.com/users/(\d+)#i', $url, $m)) {
            return SnapshotEnvelope::unreachable('stackoverflow', 'no user id in url');
        }
        $id = $m[1];

        $res = $this->http->get("https://api.stackexchange.com/2.3/users/{$id}?site=stackoverflow&filter=default");
        if (! $res->ok()) {
            return SnapshotEnvelope::unreachable('stackoverflow', $res->error ?? ('http ' . $res->status));
        }
        $data = $res->json();
        $item = $data['items'][0] ?? null;
        if (! is_array($item)) {
            return SnapshotEnvelope::unreachable('stackoverflow', 'no profile');
        }

        $env = (new SnapshotEnvelope('stackoverflow'))->reachable()->fetched();
        $reputation = (int) ($item['reputation'] ?? 0);
        $badges = (array) ($item['badge_counts'] ?? []);
        $about = trim(strip_tags((string) ($item['about_me'] ?? '')));

        $env->signal('reputation', null, $reputation)
            ->signal('gold_badges', null, (int) ($badges['gold'] ?? 0))
            ->signal('silver_badges', null, (int) ($badges['silver'] ?? 0))
            ->signal('bronze_badges', null, (int) ($badges['bronze'] ?? 0))
            ->text($about);

        // Top tags → skills + relevance text.
        $tagsRes = $this->http->get("https://api.stackexchange.com/2.3/users/{$id}/top-tags?site=stackoverflow&pagesize=15");
        $tags = [];
        if ($tagsRes->ok()) {
            foreach ((array) ($tagsRes->json()['items'] ?? []) as $t) {
                if (! empty($t['tag_name'])) {
                    $tags[] = (string) $t['tag_name'];
                }
            }
        }
        $env->addSkills($tags)->text(implode(' ', $tags));
        if ($tags !== []) {
            $env->signal('top_tags', implode(', ', array_slice($tags, 0, 10)));
        }

        $footprint = SnapshotEnvelope::logScale($reputation, 20000);
        $env->footprint($footprint)
            ->summary(sprintf('StackOverflow: %s reputation%s', number_format($reputation), $tags !== [] ? ' · ' . implode('/', array_slice($tags, 0, 4)) : ''));

        return $env->toArray();
    }
}
