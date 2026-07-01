<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Modules\AiEngine\Contracts\AiProvider;
use HaHireAI\Modules\AiEngine\Application\Exceptions\AiException;

/**
 * Registry of available AI providers. New providers are added here without
 * changing the engine or any module (docs/PROVIDER_LAYER.md).
 */
final class ProviderRegistry
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function register(AiProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): AiProvider
    {
        return $this->providers[$key] ?? throw new AiException("AI provider [{$key}] is not registered.");
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
