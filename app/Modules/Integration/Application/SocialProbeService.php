<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Core\Contracts\SocialProfileProbe;
use Throwable;

/**
 * The Integration Platform's implementation of the {@see SocialProfileProbe}
 * contract that Recruitment depends on. It routes each URL to the right adapter
 * and returns the normalised snapshot envelopes. Every adapter call is wrapped
 * so one failing/blocked source can never break the batch — the worst case is an
 * unreachable (neutral) snapshot.
 */
final class SocialProbeService implements SocialProfileProbe
{
    public function __construct(private readonly SocialAdapterRegistry $registry)
    {
    }

    /**
     * @param  list<string>  $urls
     * @return list<array<string, mixed>>
     */
    public function probe(array $urls): array
    {
        $snapshots = [];
        $seen = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '' || isset($seen[$url]) || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $seen[$url] = true;

            $adapter = $this->registry->adapterFor($url);
            if ($adapter === null) {
                $snapshots[] = ['url' => $url] + $this->neutral('other');

                continue;
            }

            try {
                $snapshot = $adapter->fetch($url);
            } catch (Throwable $e) {
                $snapshot = $this->neutral($adapter->platform(), 'adapter error');
            }
            $snapshots[] = ['url' => $url] + $snapshot;
        }

        return $snapshots;
    }

    /** @return array<string, mixed> */
    private function neutral(string $platform, ?string $error = null): array
    {
        return [
            'platform' => $platform,
            'reachable' => false,
            'fetched' => false,
            'footprint_strength' => null,
            'relevance_text' => '',
            'skills' => [],
            'signals' => [],
            'summary' => null,
            'error' => $error,
        ];
    }
}
