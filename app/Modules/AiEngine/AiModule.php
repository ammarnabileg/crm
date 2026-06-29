<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\AiEngine\Application\AiCapabilityService;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\AiEngine\Contracts\SpeechToText;
use HaHireAI\Modules\AiEngine\Infrastructure\OpenAiSpeechToText;
use HaHireAI\Modules\AiEngine\Presentation\AiController;

final class AiModule implements Module
{
    public function name(): string
    {
        return 'AiEngine';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
        // Whisper STT using each workspace's own OpenAI key (keys are isolated).
        $container->singleton(SpeechToText::class, static fn (Container $c): SpeechToText => new OpenAiSpeechToText(
            $c->make(AiSettingsService::class),
        ));

        // Which AI features are usable for a workspace (gated on its own keys).
        $container->singleton(AiCapabilities::class, static fn (Container $c): AiCapabilities => new AiCapabilityService(
            $c->make(AiSettingsService::class),
        ));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/ai', [AiController::class, 'index']);
        $router->get('/ai/analytics', [AiController::class, 'analytics']);
        $router->post('/ai/provider', [AiController::class, 'setProvider']);
        $router->post('/ai/keys', [AiController::class, 'addKey']);
        $router->post('/ai/interview-mode', [AiController::class, 'setInterviewMode']);
    }
}
