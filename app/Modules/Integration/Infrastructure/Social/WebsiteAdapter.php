<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * The catch-all adapter for personal websites, portfolios and blogs that serve
 * PUBLIC HTML (personal domains, behance, dribbble, medium, dev.to, …). It reads
 * the page and extracts the title, meta description/keywords and the first
 * heading as relevance text, plus an SSL signal. It is the LAST adapter tried,
 * so specific sources (GitHub/StackOverflow) and login-walled platforms are
 * handled first. Resilient: unreachable pages → neutral.
 */
final class WebsiteAdapter implements SocialAdapter
{
    public function __construct(private readonly HttpFetcher $http)
    {
    }

    public function key(): string
    {
        return 'website';
    }

    public function platform(): string
    {
        return 'website';
    }

    public function supports(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    public function fetch(string $url): array
    {
        $platform = $this->platformFor($url);
        $res = $this->http->get($url, ['Accept' => 'text/html,application/xhtml+xml']);

        $ssl = str_starts_with(strtolower($url), 'https://');
        if (! $res->ok()) {
            // Unreachable / dead link → neutral (never a penalty for a broken link).
            return (new SnapshotEnvelope($platform))->error($res->error ?? ('http ' . $res->status))
                ->signal('ssl', null, $ssl ? 1 : 0)->toArray();
        }

        $html = $res->body;
        $title = $this->meta($html, 'title');
        $description = $this->metaTag($html, 'description');
        $keywords = $this->metaTag($html, 'keywords');
        $h1 = $this->firstTag($html, 'h1');
        $visible = $this->visibleSnippet($html);

        $env = (new SnapshotEnvelope($platform))->reachable()->fetched()
            ->signal('ssl', null, $ssl ? 1 : 0)
            ->signal('http_status', null, $res->status)
            ->signal('title', $title)
            ->text($title)->text($description)->text($keywords)->text($h1)->text($visible);

        if ($keywords !== null) {
            $env->addSkills(array_map('trim', explode(',', $keywords)));
        }

        // Footprint: a reachable, content-bearing, https site is a modest positive.
        $contentLen = mb_strlen(trim($visible . ' ' . $title . ' ' . $description));
        $footprint = (int) round(
            ($ssl ? 18 : 6)
            + ($title !== null ? 14 : 0)
            + ($description !== null ? 14 : 0)
            + min(34, $contentLen / 60)
        );
        $env->footprint(min(80, $footprint))
            ->summary($title !== null ? mb_substr($title, 0, 120) : 'Reachable website');

        return $env->toArray();
    }

    private function platformFor(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['behance' => 'behance', 'dribbble' => 'dribbble', 'medium' => 'medium', 'dev.to' => 'devto', 'kaggle' => 'kaggle', 'gitlab' => 'gitlab'] as $needle => $name) {
            if (str_contains($host, $needle)) {
                return $name;
            }
        }

        return 'website';
    }

    private function meta(string $html, string $tag): ?string
    {
        if (preg_match('#<' . $tag . '[^>]*>(.*?)</' . $tag . '>#is', $html, $m)) {
            $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $text !== '' ? mb_substr($text, 0, 200) : null;
        }

        return null;
    }

    private function metaTag(string $html, string $name): ?string
    {
        if (preg_match('#<meta[^>]+name=["\']' . $name . '["\'][^>]+content=["\'](.*?)["\']#is', $html, $m)
            || preg_match('#<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']' . $name . '["\']#is', $html, $m)) {
            $text = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $text !== '' ? mb_substr($text, 0, 300) : null;
        }

        return null;
    }

    private function firstTag(string $html, string $tag): ?string
    {
        return $this->meta($html, $tag);
    }

    private function visibleSnippet(string $html): string
    {
        $html = preg_replace('#<(script|style|nav|footer|header)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_substr($text, 0, 1500);
    }
}
