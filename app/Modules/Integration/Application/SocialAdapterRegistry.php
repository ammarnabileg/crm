<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Application;

use HaHireAI\Modules\Integration\Contracts\SocialAdapter;

/**
 * Registry of social-source adapters. New sources are registered here (or by any
 * provider at boot) without touching the probe, the Recruitment module, or the
 * scoring engine. The first adapter whose supports() matches a URL wins, so
 * specific adapters MUST be registered before the generic website catch-all.
 */
final class SocialAdapterRegistry
{
    /** @var list<SocialAdapter> */
    private array $adapters = [];

    /** @param iterable<SocialAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(SocialAdapter $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    public function adapterFor(string $url): ?SocialAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($url)) {
                return $adapter;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(static fn (SocialAdapter $a): string => $a->key(), $this->adapters);
    }
}
