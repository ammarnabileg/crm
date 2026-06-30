<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Integration\Application\SocialAdapterRegistry;
use HaHireAI\Modules\Integration\Application\SocialProbeService;
use HaHireAI\Modules\Integration\Contracts\HttpFetcher;
use HaHireAI\Modules\Integration\Domain\HttpFetchResponse;
use HaHireAI\Modules\Integration\Infrastructure\Social\GithubAdapter;
use HaHireAI\Modules\Integration\Infrastructure\Social\RecognizedProfileAdapter;
use HaHireAI\Modules\Integration\Infrastructure\Social\WebsiteAdapter;
use PHPUnit\Framework\TestCase;

/** The pluggable social adapters + the provider-agnostic probe (no real network). */
final class SocialAdapterTest extends TestCase
{
    private function fetcher(): HttpFetcher
    {
        return new class implements HttpFetcher {
            public function get(string $url, array $headers = [], int $t = 8): HttpFetchResponse
            {
                if (str_contains($url, 'api.github.com/users/sarah/repos')) {
                    return new HttpFetchResponse(200, (string) json_encode([
                        ['name' => 'laravel-kit', 'language' => 'PHP', 'stargazers_count' => 120, 'topics' => ['laravel'], 'fork' => false],
                    ]));
                }
                if (str_contains($url, 'api.github.com/users/sarah')) {
                    return new HttpFetchResponse(200, (string) json_encode([
                        'login' => 'sarah', 'public_repos' => 32, 'followers' => 210, 'bio' => 'PHP & Laravel engineer', 'created_at' => '2014-02-01T00:00:00Z',
                    ]));
                }

                return HttpFetchResponse::failed('blocked');
            }
        };
    }

    public function test_github_adapter_collects_public_signals(): void
    {
        $env = (new GithubAdapter($this->fetcher()))->fetch('https://github.com/sarah');

        $this->assertSame('github', $env['platform']);
        $this->assertTrue($env['reachable']);
        $this->assertGreaterThan(0, $env['footprint_strength']);
        $this->assertContains('PHP', $env['skills']);
        $keys = array_column($env['signals'], 'key');
        $this->assertContains('public_repos', $keys);
        $this->assertContains('followers', $keys);
    }

    public function test_recognized_platform_is_neutral_not_scraped(): void
    {
        $env = (new RecognizedProfileAdapter())->fetch('https://www.linkedin.com/in/sarah');
        $this->assertSame('linkedin', $env['platform']);
        $this->assertFalse($env['reachable']);
        $this->assertNull($env['footprint_strength']);
    }

    public function test_unreachable_website_is_neutral_not_an_exception(): void
    {
        $env = (new WebsiteAdapter($this->fetcher()))->fetch('https://dead.example.com');
        $this->assertFalse($env['reachable']);
        $this->assertNull($env['footprint_strength']);
    }

    public function test_probe_routes_each_url_and_never_throws(): void
    {
        $registry = new SocialAdapterRegistry([
            new GithubAdapter($this->fetcher()),
            new RecognizedProfileAdapter(),
            new WebsiteAdapter($this->fetcher()),
        ]);
        $snapshots = (new SocialProbeService($registry))->probe([
            'https://github.com/sarah',
            'https://www.linkedin.com/in/sarah',
            'not a url',
        ]);

        $this->assertCount(2, $snapshots); // the invalid url is skipped
        $this->assertSame('github', $snapshots[0]['platform']);
        $this->assertSame('https://github.com/sarah', $snapshots[0]['url']);
        $this->assertSame('linkedin', $snapshots[1]['platform']);
    }

    public function test_registry_orders_specific_before_catchall(): void
    {
        $registry = new SocialAdapterRegistry([
            new GithubAdapter($this->fetcher()),
            new WebsiteAdapter($this->fetcher()),
        ]);
        $this->assertInstanceOf(GithubAdapter::class, $registry->adapterFor('https://github.com/x'));
        $this->assertInstanceOf(WebsiteAdapter::class, $registry->adapterFor('https://someblog.dev/x'));
    }
}
