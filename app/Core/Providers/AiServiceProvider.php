<?php

declare(strict_types=1);

namespace HaHireAI\Core\Providers;

use HaHireAI\Core\Config\Repository as Config;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\AiEngine\Infrastructure\Providers\EchoProvider;
use HaHireAI\Shared\Encrypter;

/**
 * Wires the AI Engine: the secret encrypter and the provider registry (the
 * built-in network-free Echo provider; real providers register here when
 * configured). The engine/settings/prompt services autowire. See docs/AI_ENGINE.md.
 */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(Encrypter::class, static fn (): Encrypter => new Encrypter(
            (string) $c->make(Config::class)->get('app.key', ''),
        ));

        $c->singleton(ProviderRegistry::class, static function (): ProviderRegistry {
            $registry = new ProviderRegistry();
            $registry->register(new EchoProvider());
            // Additional providers (OpenAI, Anthropic, Gemini, …) register here.

            return $registry;
        });
    }
}
