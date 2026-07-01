<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Integration\Application\SocialAdapterRegistry;
use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Domain\HttpFetchResponse;
use HaHireAI\Modules\Integration\Infrastructure\Social\DevToAdapter;
use HaHireAI\Modules\Integration\Infrastructure\Social\GithubAdapter;
use HaHireAI\Modules\Integration\Infrastructure\Social\GitlabAdapter;
use HaHireAI\Modules\Integration\Infrastructure\Social\WebsiteAdapter;
use PHPUnit\Framework\TestCase;

/** The new legal, unauthenticated public-API adapters (GitLab, dev.to) — no real network. */
final class NewSocialAdaptersTest extends TestCase
{
    private function fetcher(): HttpFetcher
    {
        return new class implements HttpFetcher {
            public function get(string $url, array $headers = [], int $t = 8): HttpFetchResponse
            {
                if (str_contains($url, 'gitlab.com/api/v4/users?username=sara')) {
                    return new HttpFetchResponse(200, (string) json_encode([
                        ['id' => 99, 'username' => 'sara', 'bio' => 'Go & Kubernetes', 'created_at' => '2016-01-01T00:00:00Z'],
                    ]));
                }
                if (str_contains($url, 'gitlab.com/api/v4/users/99/projects')) {
                    return new HttpFetchResponse(200, (string) json_encode([
                        ['name' => 'infra', 'description' => 'terraform modules', 'star_count' => 12, 'topics' => ['Go', 'Kubernetes', 'Terraform']],
                    ]));
                }
                if (str_contains($url, 'dev.to/api/articles?username=sara')) {
                    return new HttpFetchResponse(200, (string) json_encode([
                        ['title' => 'Scaling PHP', 'tag_list' => ['php', 'laravel'], 'positive_reactions_count' => 40],
                        ['title' => 'Redis tips', 'tag_list' => ['redis'], 'positive_reactions_count' => 15],
                    ]));
                }

                return HttpFetchResponse::failed('blocked');
            }
        };
    }

    public function test_gitlab_adapter_collects_public_signals(): void
    {
        $env = (new GitlabAdapter($this->fetcher()))->fetch('https://gitlab.com/sara');
        $this->assertSame('gitlab', $env['platform']);
        $this->assertTrue($env['reachable']);
        $this->assertContains('Kubernetes', $env['skills']);
        $this->assertGreaterThan(0, $env['footprint_strength']);
        $this->assertContains('public_projects', array_column($env['signals'], 'key'));
    }

    public function test_devto_adapter_collects_tags_as_skills(): void
    {
        $env = (new DevToAdapter($this->fetcher()))->fetch('https://dev.to/sara');
        $this->assertSame('devto', $env['platform']);
        $this->assertTrue($env['reachable']);
        // Tags are carried through as-is; the scorer canonicalises 'php' → PHP later.
        $this->assertContains('php', $env['skills']);
        $this->assertContains('articles', array_column($env['signals'], 'key'));
    }

    public function test_unreachable_is_neutral_never_throws(): void
    {
        $env = (new GitlabAdapter($this->fetcher()))->fetch('https://gitlab.com/nobody-xyz');
        $this->assertFalse($env['reachable']);
        $this->assertNull($env['footprint_strength']);
    }

    public function test_registry_routes_urls_to_the_right_adapter(): void
    {
        $f = $this->fetcher();
        $registry = new SocialAdapterRegistry([
            new GithubAdapter($f), new GitlabAdapter($f), new DevToAdapter($f), new WebsiteAdapter($f),
        ]);
        $this->assertSame('gitlab', $registry->adapterFor('https://gitlab.com/sara')?->key());
        $this->assertSame('devto', $registry->adapterFor('https://dev.to/sara')?->key());
        // A generic URL falls through to the website catch-all.
        $this->assertSame('website', $registry->adapterFor('https://sara.dev')?->key());
    }
}
